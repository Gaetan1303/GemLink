import { Component } from '@angular/core';
import { MatIconModule } from '@angular/material/icon'; 
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { RouterModule} from "@angular/router";

@Component({
  selector: 'app-footer',
  imports: [MatIconModule, MatToolbarModule, MatButtonModule, RouterModule],
  templateUrl: './footer.html',
  styleUrls: ['./footer.scss'],
})
export class Footer {}
