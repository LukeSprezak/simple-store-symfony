# Audyt projektu symfony-cqrs

Data: 2026-09-28 · Commit: `2ec4678` · Stack: Symfony 8.1, PHP 8.5, Doctrine ORM 3, MySQL 8.4, RabbitMQ 4.3, Redis 8

Zakres: architektura, poprawność domeny, bezpieczeństwo (OWASP Top 10 2021 + API Security Top 10), infrastruktura Docker, `.gitignore`, event sourcing. Nic nie zostało zmienione w kodzie.

Stan weryfikacji: `tests/Unit` przechodzi (44 testy, 22 notice PHPUnit), `composer audit` nie zgłasza podatności w zależnościach.

---

## 1. Podsumowanie (TL;DR)

| Obszar | Ocena | Najważniejsze |
|---|---|---|
| Architektura | 🟡 | Dobry podział na bounded contexty i warstwy, ale to na razie „C” bez „QRS”: brak Query Busa, read modeli i zdarzeń domenowych. Domena zależy od HTTP i Doctrine. |
| Poprawność domeny | 🔴 | Kilka realnych bugów: `Cart::isEmpty()` zawsze `false`, usunięte pozycje trafiają do zamówienia, soft-delete koszyka zależny od ostatniej pozycji, `expiresAt` nadpisywany przy każdym odczycie, nieistniejąca klasa w `OrderTransformer`. |
| Bezpieczeństwo aplikacji | 🔴 | Brak właściciela koszyka/zamówienia (IDOR/BOLA), brak limitu prób logowania, wyłączeni użytkownicy mogą się logować, CLI domyślnie nadaje `ROLE_SUPER_ADMIN`. |
| Infrastruktura | 🔴 | PHP-FPM (FastCGI) wystawiony na `0.0.0.0:9001`, Redis/Mongo/Elasticsearch/mongo-express bez uwierzytelnienia na wszystkich interfejsach. |
| Sekrety / repo | 🟡 | `APP_SECRET` w historii gita (commit `41db94a`), skrypt inicjalizujący bazę testową jest ignorowany przez `.gitignore`, `.env` nie jest w repo, a README każe go kopiować. |
| Event sourcing | ⚪ | Nie istnieje — snapshoty są na tym etapie przedwczesne (szczegóły w sekcji 6). |

---

## 2. Architektura

### 2.1 Co jest dobrze
- Podział na konteksty `Order`, `Product`, `User`, `Shared`, w każdym `Application / Domain / Infrastructure / UI`.
- Modele domenowe oddzielone od encji Doctrine (osobne klasy + transformery), repozytoria za interfejsami w `Domain`.
- Dwie szyny komend (sync / async) na Symfony Messenger, RabbitMQ + failure transport w Doctrine, retry z backoffem.
- Workflow (state machine) dla koszyka i zamówienia, Scheduler do wygaszania koszyków.
- UUID v7 (sortowalne) jako identyfikatory, `ClockInterface` w handlerze wygaszania — łatwe testowanie czasu.

### 2.2 Problemy architektoniczne

| # | Problem | Gdzie | Rekomendacja |
|---|---|---|---|
| A1 | **Brak strony „Query” w CQRS.** Nie ma `QueryBus`, `Query`, `QueryHandler`, read modeli. Kontroler koszyka czyta bezpośrednio z repozytorium domenowego. | `CartController::convertToOrder` | Dodać `Shared/Application/Bus/Query` + `command.query.bus`, projekcje / widoki SQL (DBAL, bez ORM) dla odczytów. |
| ✅ A2 | **Domena zależy od HTTP.** `AccessDeniedHttpException` rzucany w `Cart::applyTransition`, handlerach i listenerze workflow; `NotFoundHttpException` w repozytorium. | `Domain/Model/Cart.php`, `ConvertCartToOrderCommandHandler`, `RemoveProductFromCartCommandHandler`, `ProductRepository`, `CartWorkflowListener` | Wyjątki domenowe (`CartAlreadyConverted`, `ProductNotFound`), mapowanie na kody HTTP w `ExceptionListener`. |
| ✅ A3 | **Domena zależy od Doctrine.** Model domenowy `Cart`/`CartItem` używa `SoftDeleteTrait` z `Shared/Infrastructure/Doctrine` (z atrybutami `#[Column]`) i `Doctrine\Common\Collections`. | `Order/Domain/Model/*` | Soft-delete jako czysta logika domenowa (`deletedAt`), `UserRepositoryInterface` bez encji Doctrine. ✅ Zrobione. `Doctrine\Collections` w `Cart`/`Order` zostają świadomie — to samodzielna biblioteka, a A13 (mapowanie domeny przez Doctrine) i tak wymaga `Collection` w relacjach. |
| ✅ A4 | **Workflow Symfony w domenie.** `Cart::applyTransition(string, WorkflowInterface)` — agregat zna komponent frameworka. | `Cart.php` | Przejścia jako metody domenowe (`convert()`, `expire()`), a workflow tylko jako walidator w warstwie aplikacji albo w ogóle usunąć. |
| ✅ A5 | **Wyciek między kontekstami.** `Order` operuje na `Product\Domain\Model\Product` i modyfikuje jego stan (`decreaseStock`). Rezerwacja stanu magazynowego to odpowiedzialność kontekstu Product/Inventory. | `Cart::addProduct` | W `Order` trzymać `ProductId` + snapshot ceny; stan magazynu zmieniać przez komendę / zdarzenie `ProductReserved`.<br>✅ A5.1: port `Order\Domain\Service\StockReservation` (`reserve`/`release`) + adapter `ProductStockReservation`; `Cart` nie zmienia już stanu produktu.<br>✅ A5.2: `CartItem`/`OrderItem` trzymają `ProductId` + snapshot (`ProductSnapshot`: id, nazwa, cena) zamiast modelu `Product`; snapshot zwraca `StockReservation::reserve()`. Zamrożenie ceny w bazie → A10. |
| ✅ A6 | **Brak zdarzeń domenowych.** Agregaty nie emitują zdarzeń, więc nie ma podstaw pod outbox, projekcje ani event sourcing. | wszystkie agregaty | `recordThat()` / `pullDomainEvents()` + `event.bus` w Messengerze.<br>✅ `AggregateRoot` (`recordThat`/`pullDomainEvents`), `EventBus` na `event.bus` (`allow_no_handlers`), publikacja z repozytoriów `Cart`/`Order` ze stampem `DispatchAfterCurrentBusStamp` (po commicie komendy). Zdarzenia: `CartCreated`, `ProductAddedToCart`, `ProductRemovedFromCart`, `CartExpired`, `CartConverted`, `OrderPlaced`. Bez outboxa (P3) i bez zdarzeń kontekstu Product. |
| ✅ A7 | **Brak wersji/optimistic locking w domenie.** `Product` ma `#[Version]`, ale wersja nie przechodzi przez model domenowy. Ochrona działa tylko przypadkiem, gdy encja z odczytu zostaje w identity map; po ponownym pobraniu encji (wyczyszczony EM) zmiana stanu nadpisuje równoległą sprzedaż — lost update / overselling (potwierdzone symulacją). | `ProductRepository::save`, `ProductTransformer` | Przenosić `version` przez model domenowy i używać `lock($entity, LockMode::OPTIMISTIC, $version)` albo atomowy `UPDATE ... SET stock = stock - :q WHERE stock >= :q`. |
| ✅ A8 | **Martwy / zdublowany kod.** `Order/Infrastructure/Doctrine/Repository/CartRepository` (nie implementuje interfejsu, nieużywany), `TransactionMiddleware` (nie zarejestrowany — używany jest wbudowany `doctrine_transaction`), `OrderItem::toOrderItem()`, `Cart::clearItems()`, `ProductRepositoryInterface::getNextId()`, `CreateUserCommand::DEFAULT_ROLES`, `StatusOrder::RETURN_REQUESTED` (nieosiągalny w workflow). | różne | Usunąć lub podpiąć.<br>✅ Usunięte: `Doctrine\Repository\CartRepository`, `TransactionMiddleware` (+ jego test), `OrderItem::toOrderItem()`, `Cart::clearItems()`, `ProductRepository::getNextId()`/`findByIds()` (ta druga martwa po A5.1). Zostają świadomie: `CreateUserCommand::DEFAULT_ROLES` (użyje go punkt P1 o domyślnej roli), `StatusOrder::RETURN_REQUESTED` (A11). |
| ✅ A9 | **Flush w każdym `save()`** przy jednoczesnym `doctrine_transaction` middleware — wielokrotne flushe w jednej transakcji, `RemoveExpiredCartService` robi dodatkowo własny `flush()` i wstrzykuje `EntityManager` do warstwy Application. | repozytoria, `RemoveExpiredCartService` | Flush tylko w middleware (Unit of Work), usunąć `EntityManagerInterface` z warstwy Application. |
| ✅ A10 | **Pieniądze jako `float`** (`DOUBLE PRECISION`). | `Product.price`, `Cart::getTotalAmount` | `Money` VO na int (grosze) lub `DECIMAL(10,2)` + `brick/money`. Cena powinna być zamrażana w `OrderItem` w momencie zamówienia.<br>✅ A10.1: `Shared\Domain\ValueObject\Money` (int, grosze); `Product`/`ProductSnapshot`/`Cart::getTotalAmount()` na `Money`, `product.price` INT (migracja przelicza istniejące ceny), API dalej przyjmuje liczbę dziesiętną.<br>✅ A10.2: `order_item.unit_price` (INT) zapisywana przy tworzeniu zamówienia, `OrderTransformer` czyta cenę z pozycji zamiast z produktu; migracja uzupełnia istniejące pozycje bieżącą ceną. |
| ✅ A11 | **Workflow `order_state` jest niespójny z encją.** Marking store `method` wywoła `setStatus(string)`, a encja przyjmuje `StatusOrder` → `TypeError` przy pierwszym przejściu. Workflow wskazuje encję Doctrine, a `cart_state` — model domenowy. | `workflow.yaml`, `Infrastructure/Doctrine/Entity/Order.php` | Ujednolicić (domena) i dodać test przejść.<br>✅ Workflow `order_state` usunięty (`config/packages/workflow.yaml`); tabela przejść w `StatusOrderTransition::allowedFrom()`/`target()` + `Order::apply()` (409 przy niedozwolonym przejściu). Ścieżka zwrotu: DELIVERED → RETURN_REQUESTED → AWAITING_RECEIPT → RETURNED, status `RETRIEVED` usunięty. Test: `tests/Unit/Order/Domain/Model/OrderTest.php`. |
| ✅ A12 | **Brak narzędzi jakości:** PHPStan/Psalm, Deptrac (pilnowanie zależności warstw), Rector, CI. | — | PHPStan lvl max + Deptrac z regułą „Domain nie zależy od niczego poza Shared\Domain”.<br>✅ A12.1: PHPStan level max (+ phpstan-symfony/doctrine/phpunit), `phpstan.dist.neon` + `phpstan-baseline.neon` (257 istniejących błędów, patrz A14).<br>✅ A12.2: Deptrac (`deptrac.yaml`): warstwy Domain → ∅, Application → Domain, Infrastructure → Domain+Application, UI → wszystkie; 0 naruszeń, reguła sprawdzona celowym naruszeniem. `/.deptrac.cache` w `.gitignore`.<br>✅ A12.3: GitHub Actions (`.github/workflows/ci.yml`): MySQL + RabbitMQ jako serwisy; composer validate/audit, php-cs-fixer, PHPStan, Deptrac, lint:container/yaml, migracje + schema:validate, PHPUnit. Kroki odtworzone lokalnie na czystej kopii repo. |
| A13 | **Ręczne transformery encja ↔ domena** (`CartTransformer`, `OrderTransformer`, `ProductTransformer`, `UserTransformer`) — podwójne modele, ręczna synchronizacja kolekcji, źródło bugów B3/B4/B7 i nieskutecznego `#[Version]` (A7). ObjectMapper tego nie upraszcza (prywatne `readonly` pola, relacje wymagające `EntityManager`, VO ↔ string — logika zostaje w transform-serwisach). | `Order/Infrastructure/Transformer`, `Product/Infrastructure/Transformer`, `User/Infrastructure/Transformer`, `*/Infrastructure/Doctrine/Entity` | Mapować modele domenowe bezpośrednio przez Doctrine (XML/PHP mapping w `Infrastructure/Doctrine/Mapping`, custom DBAL type/embeddable dla `UserId`), usunąć encje-duplikaty i transformery. ObjectMapper ewentualnie dla Request DTO → Command i DTO odpowiedzi/read modeli.<br>✅ A13.1: Product — XML mapping domeny (`Product/Infrastructure/Doctrine/Mapping`), typy DBAL `user_id`/`money`; usunięte encja `Product` i `ProductTransformer`; zdjęty FK `product.user_id` i nieużywana `sales_count`.<br>✅ A13.2: Cart — XML mapping `Cart`/`CartItem` + embeddable `ProductSnapshot` (`product_id`/`product_name`/`product_price` w `cart_item`, cena zamrażana przy dodaniu); usunięte encje `Cart`/`CartItem`, `CartTransformer`, `SoftDeleteTrait`; zdjęty FK `cart_item.product_id` i kolumna `deleted`. PHPStan dostał `objectManagerLoader`.<br>✅ A13.3: Order — XML mapping `Order`/`OrderItem` + embeddable `ProductSnapshot` w `order_item` (`unit_price` → `product_price`, nowe `product_name` — zamrożone też nazwy); usunięte encje `Order`/`OrderItem`, `OrderTransformer`, atrybutowe mapowanie `App\Order`; zdjęty FK `order_item.product_id` i nieużywane `order.updated_at`.<br>A13.4: sprzątanie User (domenowy `User`, `UserTransformer`, `UserRepository::findEntityById`). |
| A14 | **Błędy PHPStan w baseline (257).** Najważniejsze z poziomów ≤5: `JsonBodyResolver` woła `ValidationError` z 2 argumentami (konstruktor przyjmuje 1 — przyczyna błędu ginie); niezgodności mapowania Doctrine (`Product::$description` i `User::$password` nullable vs NOT NULL, `CartItem::$product` nullable w bazie); nieużywane settery w `User` (domena) i `Product::$reviews`; mocki w `CreateUserCommandTest` bez typu `MockObject`. Reszta (poziomy 6–10): brak typów generycznych tablic/kolekcji, `mixed`. | `phpstan-baseline.neon` | Usuwać wpisy z baseline'u stopniowo, zaczynając od poziomów ≤5. |

### 2.3 Bugi w logice domeny (potwierdzone czytaniem kodu)

| # | Bug | Skutek |
|---|---|---|
| ✅ B1 | `Cart::isEmpty()` → `empty($this->items)` na obiekcie `Collection` zawsze zwraca `false`. | Pusty koszyk da się zamienić w zamówienie. |
| ✅ B2 | `ConvertCartToOrderCommandHandler` iteruje po wszystkich `getItems()`, także soft-deleted. | Produkty usunięte z koszyka trafiają do zamówienia. |
| ✅ B3 | `CartTransformer::toDomain` ustawia `setDeleted/At` **koszyka** na podstawie każdej pozycji w pętli (wygrywa ostatnia). `fromDomain` ustawia flagę nowej pozycji na podstawie **koszyka**. | Usunięcie ostatniej pozycji „blokuje” cały koszyk (`Access to this resource is locked.`), nowe pozycje mogą dostać flagę `deleted`. |
| ✅ B4 | `CartTransformer::toDomain` używa `Cart::create()`, które ustawia `createdAt = now`, `expiresAt = now + 24h`; encja ma `expiresAt = createdAt + 1 minute` i nigdy nie jest aktualizowana z domeny. | Dwa różne czasy wygaśnięcia, model domenowy nigdy nie jest „przeterminowany”. |
| ✅ B5 | `AddProductToCartCommandHandler` nie sprawdza statusu koszyka. | Można dodawać produkty do koszyka `expired` / `converted_to_order` (i zdejmować stan magazynu). |
| ✅ B6 | `RemoveProductFromCart` nie zwraca stanu magazynowego; wraca on dopiero przy wygaśnięciu koszyka (który liczy też usunięte pozycje), a po konwersji — nigdy. | Rozjazd stanów magazynowych. |
| ✅ B7 | `OrderTransformer::toDomain` tworzy `\App\Order\Domain\Model\Product` — ta klasa nie istnieje; `OrderItem` użyty bez importu. | Fatal error przy każdym `OrderRepository::find()`. |
| ✅ B8 | `ProductRepository::get` rzuca `NotFoundHttpException`, a handlery łapią `ProductNotFoundException`. W `AddProductToCartCommandHandler` wyjątek jest opakowywany w `RuntimeException`. | 500 zamiast 404. |
| ✅ B9 | `Cart::addProduct` rzuca `ProductUnavailableException('Not enough stock for product: '.$name)`, a konstruktor oczekuje ID i formatuje komunikat ponownie. `OrderCreateException` ignoruje przekazany `$previous` (konstruktor ma 1 parametr, a handler przekazuje 3). | Zniekształcone komunikaty, utrata przyczyny błędu w logach. |
| ✅ B10 | `Order::create($cart->getId(), ...)` — ID zamówienia = ID koszyka; `OrderItem` ID = `CartItem` ID. | Działa, ale sprzęga cykle życia; ponowna konwersja (np. po `abandon`) kończy się konfliktem PK. |
| ✅ B11 | Scheduler co 5 s bez `lock`/`stateful` — przy więcej niż jednym workerze `scheduler_expired_cart` ten sam koszyk może być wygaszony równolegle (podwójny zwrot stanu). | Stan magazynu zawyżony. |
| ✅ B12 | `ProductController::remove` robi twarde `DELETE` produktu, do którego odwołują się `cart_item`/`order_item`/`review` (FK). | Komenda async zawsze wyląduje w `failed`. Potrzebny soft-delete/status `INACTIVE`. |
| ✅ B13 | Asynchroniczne komendy zwracają `204 No Content`. | Klient myśli, że operacja się wykonała; poprawnie `202 Accepted` + ID zasobu. |

---

## 3. Bezpieczeństwo — OWASP

Mapowanie na **OWASP Top 10 (2021)** oraz **OWASP API Security Top 10 (2023)**.

### A01 Broken Access Control / API1 BOLA / API5 BFLA — 🔴 krytyczne
- ✅ **Koszyk i zamówienie nie mają właściciela.** Brak `user_id` w `cart` i `order`. Każdy zalogowany `ROLE_USER` znający `cartId` może dodawać/usuwać produkty i konwertować cudzy koszyk. `cartId` jest przyjmowany z URL (`/api/cart/add-product/{cartId}`) — klient może też **sam wybrać ID** nowego koszyka.
  → Dodać `ownerId`, sprawdzać go w handlerach (lub Voter), ID koszyka generować wyłącznie po stronie serwera.
  - ✅ Koszyk: `owner_id` w `cart`, sprawdzany w handlerach add/remove/convert (cudzy koszyk → 404), nowy koszyk tylko z ID generowanym przez serwer.
  - ✅ Zamówienie: `owner_id` w `order`, przenoszony z koszyka przy konwersji.
- **Parametry ścieżki bez walidacji formatu** (`{cartId}`, `{id}`) — dodać `requirements: ['cartId' => Requirement::UUID_V4]`.
- ✅ **Brak `role_hierarchy`** — `ROLE_SUPER_ADMIN` nie dziedziczy `ROLE_ADMIN`; dziś działa tylko dzięki temu, że `getRoles()` dokleja `ROLE_USER`.
- ✅ **Brak UserCheckera** — pole `enabled` nie jest sprawdzane przy logowaniu ani przy weryfikacji JWT. Wyłączony użytkownik zachowuje dostęp.

### A02 Cryptographic Failures — 🟡
- `APP_SECRET` z wartością jest w historii gita (commit `41db94a`, usunięty dopiero w `2ec4678`) → **zrotować** i traktować jako skompromitowany; ewentualnie wyczyścić historię (`git filter-repo`) jeśli repo jest publiczne.
- `JWT_PASSPHRASE` jest pusty w `.env` — klucz prywatny JWT niezaszyfrowany. Na produkcji passphrase w Symfony Secrets / vault.
- Kolumny `token`, `reset_password_token` przechowywane w plaintext — przechowywać hash (SHA-256) i porównywać `hash_equals`.
- Kolumna `plain_password` w bazie — nie powinna istnieć (pole tylko w pamięci, bez `#[Column]`). `repeat_password` jako druga kopia hasha — zbędna, usunąć.

### A03 Injection — 🟢
- Zapytania przez QueryBuilder z parametrami, brak surowego SQL z danymi użytkownika. OK.
- `JsonBodyResolver` używa `DISABLE_TYPE_ENFORCEMENT` — złe typy kończą się `TypeError` (500) zamiast 400. Lepiej standardowy `#[MapRequestPayload]` bez własnego resolvera.

### A04 Insecure Design / API6 Unrestricted Access to Sensitive Business Flows — 🔴
- **Rezerwacja stanu magazynowego przy dodaniu do koszyka bez limitów** → atak „inventory hoarding”: jeden użytkownik może zdjąć cały stan każdego produktu na 24 h (lub 1 min — patrz B4), tworząc dowolną liczbę koszyków. Potrzebne: limit ilości na pozycję, limit aktywnych koszyków na użytkownika, krótszy TTL rezerwacji.
- ✅ **Race condition na stanie magazynowym** (A7) — brak atomowej dekrementacji / optimistic lock → overselling.
- Cena nie jest zamrażana w zamówieniu — zmiana ceny produktu zmienia wartość historycznych zamówień.

### A05 Security Misconfiguration — 🔴
- **Docker (dev, ale uruchamiany na hoście dewelopera):**
  - ✅ `php85` wystawia `9001:9000` (FastCGI) na `0.0.0.0` → nieuwierzytelniony FastCGI = zdalne wykonanie kodu z każdej maszyny w sieci. Usunąć mapowanie portu (nginx łączy się po sieci Dockera).
  - ✅ Redis `6379` bez hasła, Elasticsearch `9200` z `xpack.security.enabled=false`, Kibana `5601`, mongo-express `8081` z `ME_CONFIG_BASICAUTH: false`, MongoDB `27017`, phpMyAdmin, RabbitMQ management — wszystko na `0.0.0.0`. Bindować do `127.0.0.1:` albo przenieść do `profiles:` i nie uruchamiać domyślnie.
  - ✅ Mongo, mongo-express, Elasticsearch, Kibana nie są używane przez aplikację — usunąć lub przenieść do profilu.
  - ✅ mongo-express ma hasło `example`, a Mongo `password` — konfiguracja i tak nie działa.
  - Xdebug `start_with_request=yes` zawsze włączony w obrazie; `memory_limit=-1`; `client_max_body_size 108M` dla API JSON (wystarczy ~1M).
  - Dockerfile: `ADD .../releases/latest/...` i `curl | bash` (niezweryfikowane, nieprzypięte wersje), Node.js + nodemon + yarn niepotrzebne w API, brak `USER`, brak osobnego obrazu prod.
  - Obrazy `phpmyadmin/phpmyadmin`, `mongo`, `mongo-express`, `nginx:stable-alpine` bez przypiętej wersji.
- **Symfony:**
  - ✅ `security.yaml`: hasher dla nieistniejącej klasy `App\Entity\User`; `logout` na firewallu stateless JWT nie ma sensu.
  - `framework.session` włączona, choć API jest bezstanowe — wyłączyć (`session: false`).
  - `ExceptionListener` zwraca `trace` dla każdego env ≠ `prod` (np. `staging`) — warunek powinien opierać się na `kernel.debug`.
  - Brak `trusted_proxies` / `trusted_headers` — za load balancerem `getClientIp()` zwróci IP proxy i rate limiter zadziała globalnie.
  - Brak CORS (`nelmio/cors-bundle`) — jeśli API ma być wołane z przeglądarki.
- **nginx:** brak nagłówków bezpieczeństwa (`X-Content-Type-Options: nosniff`, `Strict-Transport-Security`, `Content-Security-Policy: default-src 'none'`, `Referrer-Policy`), brak `server_tokens off`, brak TLS.

### A06 Vulnerable and Outdated Components — 🟢
- `composer audit`: brak podatności. Elasticsearch/Kibana 7.17 (EOL) — usunąć albo zaktualizować do 8.x/9.x. Dodać `composer audit` do CI + Dependabot/Renovate.

### A07 Identification and Authentication Failures / API2 — 🔴
- ✅ **Brak ograniczenia prób logowania.** `RateLimiterSubscriber` ma priorytet 0 i działa **po** firewallu; `/api/login_check` jest obsługiwany przez `json_login` w firewallu, więc limiter nigdy go nie widzi. Użyć wbudowanego `login_throttling` na firewallu `login` (`max_attempts: 5, interval: '15 minutes'`).
- Limiter nazwany `anonymous_api` w praktyce limituje **zalogowanych** użytkowników po IP (5 req/min — bardzo nisko dla API). Rozdzielić: limit na IP dla anonimowych, na `userId` dla zalogowanych.
- JWT: TTL 3600 s bez refresh tokenów i bez możliwości unieważnienia (logout, zmiana hasła, blokada konta). Rozważyć `gesdinet/jwt-refresh-token-bundle`, krótszy TTL (5–15 min), weryfikację `lastPasswordChange` vs `iat`.
- `user_id_claim: email` — e-mail (PII) w każdym tokenie; lepiej UUID.
- `email` ma limit 32 znaków (za mało, RFC: 254). `#[UniqueEntity(fields: ['username', 'email'])]` sprawdza unikalność **pary**, a nie każdego pola osobno.
- `app:create-user`: domyślna odpowiedź na pytanie o role to **wszystkie role, w tym `ROLE_SUPER_ADMIN`** — wciśnięcie Enter tworzy superadmina. Domyślny e-mail zahardkodowany. Minimalna długość hasła 8 bez `NotCompromisedPassword`.

### A08 Software and Data Integrity Failures — 🟡
- Messenger używa `symfony_serializer` (JSON), nie `PhpSerializer` → brak ryzyka deserializacji obiektów PHP. OK.
- RabbitMQ: `user/password` z domyślnego env — każdy z dostępem do brokera może wstrzyknąć komendę (np. `RemoveProductCommand`). Na prod: osobny vhost, użytkownik z minimalnymi uprawnieniami, TLS; rozważyć podpisywanie wiadomości (`SigningSerializer` / stamp z HMAC).
- Dockerfile pobiera skrypty przez `curl | bash` bez sum kontrolnych.

### A09 Security Logging and Monitoring Failures — 🟡
- Brak logowania zdarzeń bezpieczeństwa: nieudane logowania, 403, 429, zmiany ról. Dodać listener na `LoginFailureEvent` / `AccessDeniedException` do kanału `security`.
- `ExceptionListener` loguje tylko błędy 5xx; brak correlation-id / request-id w logach i w wiadomościach Messengera.
- Brak audytu zmian stanu zamówienia (`audit_trail: enabled: false` w workflow).

### A10 SSRF — 🟢
- Aplikacja nie wykonuje żądań wychodzących na podstawie danych użytkownika.

### API4 Unrestricted Resource Consumption — 🟡
- Brak limitu `quantity` (tylko `>= 1`) — `PHP_INT_MAX` w żądaniu.
- `client_max_body_size 108M`, `post_max_size 108M` dla API JSON.
- Brak paginacji (na razie brak endpointów listujących — pamiętać przy dodawaniu Query side).

---

## 4. Sekrety i `.gitignore`

### 4.1 Problemy z obecnym stanem
1. **`.env` nie jest w repozytorium** (usunięty w `2ec4678` i dodany do `.gitignore`), a README instruuje `cp .env .env.local`. Świeży klon nie ma skąd wziąć zmiennych. Konwencja Symfony: `.env` z bezpiecznymi wartościami domyślnymi **jest commitowany**, sekrety tylko w `.env.local` / Symfony Secrets. Alternatywnie commitować `.env.dist`.
2. **`/etc/docker/sql/*` ignoruje `etc/docker/sql/init/01-test-database.sql`**, który jest montowany do `docker-entrypoint-initdb.d` i tworzy bazę `dbname_test`. Na świeżym klonie testy integracyjne nie zadziałają. Zmienić na `/etc/docker/sql/*` + `!/etc/docker/sql/init/`.
3. `/.env.test` jest ignorowany — w Symfony `.env.test` zwykle się commituje (bez sekretów); dziś zmienne testowe są w `phpunit.xml.dist`, więc OK, ale jest to niespójne z `when@test` konfiguracją.
4. Duplikaty w `.gitignore`: `.phpunit.result.cache` i `/phpunit.xml` (dwa razy), `/.env.test` i `.env.test`.
5. `APP_SECRET` w historii — patrz A02.

### 4.2 Proponowane dopiski do `.gitignore`

```gitignore
###> OS / edytory ###
.DS_Store
Thumbs.db
.vscode/
*.swp
*~
###< OS / edytory ###

###> PHPUnit 10+ / coverage ###
/.phpunit.cache/
/coverage/
/build/
/var/coverage/
###< PHPUnit ###

###> narzędzia QA (gdy zostaną dodane) ###
/phpstan.neon
/.phpstan.cache/
/psalm.xml
/.deptrac.cache
/.rector.cache/
###< narzędzia QA ###

###> sekrety / klucze ###
*.pem
*.key
*.p12
/config/secrets/*/*.decrypt.private.php
###< sekrety ###

###> Docker ###
docker-compose.override.yml
compose.override.yaml
###< Docker ###

###> Node (Dockerfile instaluje node/yarn) ###
node_modules/
/public/build/
###< Node ###

###> Claude Code – lokalne ustawienia ###
.claude/settings.local.json
###< Claude Code ###

*.log
```

Oraz poprawka istniejącego wpisu:

```gitignore
/etc/docker/sql/*
!/etc/docker/sql/init/
```

`/config/secrets/prod/prod.decrypt.private.php` warto uogólnić na `/config/secrets/*/*.decrypt.private.php` (obejmie `dev`, `staging`).

---

## 5. Testy

- Unit: 44 testy / 238 asercji — przechodzą, ale z 22 notice PHPUnit (do przejrzenia: prawdopodobnie mocki bez oczekiwań → `createStub`).
- Brak testów: `ConvertCartToOrderCommandHandler`, `RemoveProductFromCartCommandHandler`, transformerów `Order`, workflow `order_state`, kontrolerów (functional), autoryzacji (czy `ROLE_USER` nie wejdzie w `/api/product`), rate limitu na logowaniu.
- Testy, które wykryłyby bugi B1–B7: pusty koszyk → wyjątek; konwersja po usunięciu pozycji; round-trip `toDomain(fromDomain(x)) == x` dla koszyka; `OrderRepository::find`.
- Brak CI (GitHub Actions): `phpunit`, `php-cs-fixer --dry-run`, `phpstan`, `composer audit`, `lint:container`, `doctrine:schema:validate`.

---

## 6. Event sourcing i snapshoty

### 6.1 Stan obecny
Projekt **nie ma event sourcingu** — agregaty są zapisywane jako stan (ORM), nie ma zdarzeń domenowych, event store’a ani projekcji. Snapshoty są optymalizacją *odczytu strumienia zdarzeń*, więc dziś nie ma czego snapshotować.

### 6.2 Czy warto?
- **Koszyk (`Cart`)** — krótkotrwały (24 h), kilka–kilkanaście zdarzeń. Event sourcing ma sens (analityka porzuceń), **snapshoty — nie**: odtworzenie 20 zdarzeń trwa mikrosekundy.
- **Zamówienie (`Order`)** — naturalny kandydat na ES (audyt przejść statusów, zwroty, płatności — wymogi prawne/księgowe). Typowo < 50 zdarzeń na zamówienie → **snapshoty też zbędne**.
- **Produkt / stan magazynowy** — jedyny agregat, którego strumień rośnie bez końca (każda rezerwacja, zwrot, sprzedaż). Tu snapshoty **mają sens** — albo lepiej: modelować stan magazynowy jako osobny agregat `StockItem` z zamykaniem okresów (`StockPeriodClosed`), co ogranicza długość strumienia.

### 6.3 Rekomendowana kolejność
1. **Zdarzenia domenowe** w agregatach (`CartCreated`, `ProductAddedToCart`, `ProductRemovedFromCart`, `CartExpired`, `CartConverted`, `OrderPlaced`, `OrderStatusChanged`, `StockReserved`, `StockReleased`) + `event.bus`.
2. **Transactional Outbox** — zapis zdarzeń w tej samej transakcji co stan, publikacja do RabbitMQ przez worker. Rozwiązuje dziś brak gwarancji dostarczenia między DB a brokerem.
3. **Read modele / projekcje** zasilane zdarzeniami (dopełnia CQRS — A1).
4. **Event sourcing tylko dla `Order`** (i ewentualnie `Cart`) — biblioteka `patchlevel/event-sourcing` (natywna integracja z Symfony, wspiera snapshoty, upcasting, projekcje) lub `EventSauce`. Własna implementacja nie jest opłacalna.
5. **Snapshoty** dopiero gdy pomiar pokaże, że strumień agregatu przekracza ~100–500 zdarzeń lub ładowanie jest wolne. Wymagania, gdy przyjdzie czas:
   - snapshot co N zdarzeń (np. 100), przechowywany w Redis/osobnej tabeli, z `aggregateVersion` i `schemaVersion`,
   - odrzucanie snapshotu przy zmianie `schemaVersion` (odbudowa ze strumienia),
   - snapshot to tylko cache — zawsze musi dać się go usunąć bez utraty danych.

---

## 7. Priorytety (co zrobić w pierwszej kolejności)

| Priorytet | Zadanie | Sekcja |
|---|---|---|
| ✅ P0 | Usunąć mapowanie portu `9001:9000`, zbindować usługi do `127.0.0.1`, wyrzucić nieużywane Mongo/ES/Kibana | A05 |
| P0 | Zrotować `APP_SECRET` | A02 |
| ✅ P0 | Właściciel koszyka + sprawdzanie w handlerach, ID koszyka generowane przez serwer | A01 |
| ✅ P0 | Właściciel zamówienia (przeniesiony z koszyka przy konwersji) | A01 |
| ✅ P0 | `login_throttling` na firewallu `login`, UserChecker dla `enabled` | A07 |
| P1 | Naprawić B1–B8 (+ testy regresyjne) | 2.3 |
| ✅ P1 | Optimistic lock na stanie magazynowym | A7 |
| P1 | Limity ilości na pozycję i aktywnych koszyków na użytkownika | A04 |
| P1 | `app:create-user` — domyślnie `ROLE_USER` | A07 |
| P1 | `.gitignore`: odignorować `etc/docker/sql/init/`, przywrócić `.env` (bez sekretów) do repo | 4 |
| ✅ P2 | Wyjątki domenowe zamiast HTTP w domenie | A2 |
| ✅ P2 | Usunięcie zależności domeny od Infrastructure (soft-delete, repozytorium użytkownika) | A3 |
| ✅ P2 | Usunięcie zależności domeny od Workflow | A4 |
| P2 | Query Bus + read modele | A1 |
| ✅ P2 | Pieniądze jako `Money`/int, zamrażanie ceny w `OrderItem` | A10 |
| ✅ P2 | PHPStan + Deptrac + CI | A12, 5 |
| P2 | Mapowanie domeny bezpośrednio przez Doctrine zamiast transformerów | A13 |
| P3 | Zdarzenia domenowe → outbox → projekcje → ES dla `Order` | 6 |
| P3 | Snapshoty — tylko po pomiarze | 6 |
