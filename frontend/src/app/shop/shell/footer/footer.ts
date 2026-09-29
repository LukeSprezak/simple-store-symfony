import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SUPPORT_EMAIL } from '../../contact';

@Component({
  selector: 'app-footer',
  imports: [RouterLink],
  templateUrl: './footer.html',
  styleUrl: './footer.css',
})
export class Footer {
  protected readonly year = new Date().getFullYear();
  protected readonly supportEmail = SUPPORT_EMAIL;
}
