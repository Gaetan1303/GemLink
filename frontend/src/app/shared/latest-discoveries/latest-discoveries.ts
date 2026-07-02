import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-latest-discoveries',
  imports: [CommonModule],
  templateUrl: './latest-discoveries.html',
  styleUrls: ['./latest-discoveries.scss'],
})
export class LatestDiscoveries {
  counter = signal(0);

  increment() {
    this.counter.update(value => value + 1);
  }
}

