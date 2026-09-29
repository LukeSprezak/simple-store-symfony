import { CurrencyPipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { map } from 'rxjs';

interface SlideProduct {
  id: string;
  name: string;
  description: string;
  priceInCents: number;
}

@Component({
  selector: 'app-slider',
  imports: [CurrencyPipe],
  template: `
    @if (products().length) {
      <section class="slider" aria-label="Featured products">
        <div class="viewport">
          <div class="track">
            <!-- The list is rendered twice so the -50% shift loops seamlessly. -->
            @for (product of loop(); track $index) {
              <article class="slide" [attr.aria-hidden]="$index >= products().length">
                <strong class="name">{{ product.name }}</strong>
                <span class="description">{{ product.description }}</span>
                <span class="price">{{ product.priceInCents / 100 | currency }}</span>
              </article>
            }
          </div>
        </div>
      </section>
    }
  `,
  styles: `
    .slider {
      padding: 24px 0;
      background: var(--primary);
    }

    .viewport {
      overflow: hidden;
      padding: 0 0 4px;
      mask-image: linear-gradient(to right, transparent, #000 5%, #000 95%, transparent);
    }

    .track {
      display: flex;
      gap: 20px;
      width: max-content;
      animation: scroll 60s linear infinite;
    }

    .slider:hover .track {
      animation-play-state: paused;
    }

    .slide {
      display: flex;
      flex-direction: column;
      gap: 4px;
      width: 240px;
      padding: 16px 20px;
      border: 1px solid var(--text);
      border-radius: 12px;
      box-shadow: var(--shadow);
      background: var(--surface);
    }

    .name {
      font: 700 18px Ubuntu, sans-serif;
    }

    .description {
      overflow: hidden;
      color: var(--muted);
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .price {
      color: var(--primary);
      font: 700 18px Ubuntu, sans-serif;
    }

    @keyframes scroll {
      to {
        transform: translateX(calc(-50% - 10px));
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .viewport {
        overflow-x: auto;
      }

      .track {
        animation: none;
      }
    }
  `,
})
export class Slider {
  protected readonly products = toSignal(
    inject(HttpClient)
      .get<{ items: SlideProduct[] }>('/api/product', { params: { limit: 10 } })
      .pipe(map((page) => page.items)),
    { initialValue: [] },
  );
  protected readonly loop = computed(() => [...this.products(), ...this.products()]);
}
