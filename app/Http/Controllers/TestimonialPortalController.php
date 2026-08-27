<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\View\View;

class TestimonialPortalController extends Controller
{
    public function index(): View
    {
        return view('testimonials.index', ['testimonials' => Testimonial::query()->where('status', 'published')->where('consent_given', true)->whereNotNull('published_at')->where('published_at', '<=', now())->orderBy('sort_order')->latest('published_at')->paginate(12)]);
    }
}
