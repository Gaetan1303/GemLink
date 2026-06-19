import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { SharedModule } from '../shared/shared-module';

@Component({
  selector: 'app-form',
  imports: [CommonModule, ReactiveFormsModule, SharedModule],
  templateUrl: './form.html',
  styleUrls: ['./form.scss'],
})
export class Form implements OnInit {
  loginForm!: FormGroup;
  isSubmitted = false;

  constructor(private fb: FormBuilder) {}

  ngOnInit(): void {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', Validators.required]
    });
  }

  onSubmit(): void {
    this.isSubmitted = true;
    
    if (this.loginForm.invalid) {
      return;
    }

    
  }
}