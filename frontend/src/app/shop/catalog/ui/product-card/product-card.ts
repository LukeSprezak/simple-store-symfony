import { CurrencyPipe } from '@angular/common';
import { Component, computed, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CentsPipe } from '../../../../shared/ui/cents.pipe';
import { isInStock, Product } from '../../domain/product';

@Component({
  selector: 'app-product-card',
  imports: [CentsPipe, CurrencyPipe, RouterLink],
  templateUrl: './product-card.html',
  styleUrl: './product-card.css',
  host: { class: 'panel' },
})
export class ProductCard {
  readonly product = input.required<Product>();
  readonly adding = input(false);
  readonly add = output<Product>();
  protected readonly inStock = computed(() => isInStock(this.product()));
}
