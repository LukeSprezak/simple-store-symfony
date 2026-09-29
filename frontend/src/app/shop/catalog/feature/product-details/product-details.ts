import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, input, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../../../shared/http/api';
import { CentsPipe } from '../../../../shared/ui/cents.pipe';
import { addableQuantity, CartStore, MAX_QUANTITY_PER_PRODUCT, quantityInCart } from '../../../cart';
import { ProductApi } from '../../data-access/product.api';
import { isInStock, Product, productCode } from '../../domain/product';
import { QuantityStepper } from '../../ui/quantity-stepper/quantity-stepper';

@Component({
  selector: 'app-product-details',
  imports: [CentsPipe, CurrencyPipe, QuantityStepper, RouterLink],
  templateUrl: './product-details.html',
  styleUrl: './product-details.css',
})
export class ProductDetails implements OnInit {
  readonly id = input.required<string>();
  private readonly api = inject(ProductApi);
  private readonly cart = inject(CartStore);
  protected readonly maxPerProduct = MAX_QUANTITY_PER_PRODUCT;
  protected readonly product = signal<Product | null>(null);
  protected readonly notFound = signal(false);
  protected readonly adding = signal(false);
  protected readonly added = signal(false);
  protected readonly error = signal('');
  protected readonly quantity = signal(1);
  protected readonly inStock = computed(() => isInStock(this.product()!));
  protected readonly code = computed(() => productCode(this.product()!));
  protected readonly inCart = computed(() => quantityInCart(this.cart.cart(), this.id()));
  protected readonly maxQuantity = computed(() => addableQuantity(this.inCart(), this.product()?.stockQuantity ?? 0));

  ngOnInit(): void {
    this.loadProduct();
  }

  protected add(product: Product): void {
    this.adding.set(true);
    this.added.set(false);
    this.error.set('');
    this.cart.add(product.id, this.quantity()).subscribe({
      next: () => {
        this.adding.set(false);
        this.added.set(true);
        this.quantity.set(1);
        // Adding reserves stock, so the available quantity has changed.
        this.loadProduct();
      },
      error: (error: HttpErrorResponse) => {
        this.adding.set(false);
        this.error.set(apiErrorMessage(error, `Could not add ${product.name} to the cart.`));
      },
    });
  }

  private loadProduct(): void {
    this.api.get(this.id()).subscribe({
      next: (product) => this.product.set(product),
      error: () => this.notFound.set(true),
    });
  }
}
