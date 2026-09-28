import { JsonPipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';

@Component({
  selector: 'app-home',
  imports: [JsonPipe],
  template: `
    <main class="container">
      <h1>Dashboard</h1>
      <section class="panel">
        <h2>API</h2>
        <pre>{{ api() | json }}</pre>
      </section>
    </main>
  `,
  styles: `
    pre {
      margin: 0;
      padding: 16px;
      background: var(--bg);
      border-radius: var(--radius);
      overflow: auto;
    }
  `,
})
export class Home {
  protected readonly api = toSignal(inject(HttpClient).get('/api'));
}
