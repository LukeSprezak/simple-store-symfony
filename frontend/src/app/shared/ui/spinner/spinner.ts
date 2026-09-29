import { Component, input } from '@angular/core';

@Component({
  selector: 'app-spinner',
  template: '',
  styleUrl: './spinner.css',
  host: {
    role: 'status',
    'aria-label': 'Loading',
    '[style.width.px]': 'size()',
    '[style.height.px]': 'size()',
    '[style.border-width.px]': 'size() > 16 ? 3 : 2',
  },
})
export class Spinner {
  readonly size = input(28);
}
