import { Pipe, PipeTransform } from '@angular/core';

// The API sends money as integer cents; chain with `currency` to display it.
@Pipe({ name: 'cents' })
export class CentsPipe implements PipeTransform {
  transform(cents: number): number {
    return cents / 100;
  }
}
