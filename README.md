- cp .env .env.local
- php bin/console lexik:jwt:generate-keypair
- php bin/console d:s:u --force
- php bin/console app:create-user

