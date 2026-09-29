import { Component, computed, input } from '@angular/core';
import { statusLabel } from '../../../../shared/domain/order-status';
import { statusTone } from '../../domain/order';

@Component({
  selector: 'app-status-badge',
  templateUrl: './status-badge.html',
})
export class StatusBadge {
  readonly status = input.required<string>();
  protected readonly label = computed(() => statusLabel(this.status()));
  protected readonly tone = computed(() => statusTone(this.status()));
}
