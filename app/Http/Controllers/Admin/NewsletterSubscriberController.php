<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request)
    {
        $subscribers = $this->subscribersQuery($request->get('search'))
            ->paginate(50)
            ->withQueryString();

        return view('admin.newsletter.index', compact('subscribers'));
    }

    public function export()
    {
        $filename = 'mailsoar-newsletter-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Email', 'Consent date', 'Last test', 'Tests', 'Language']);

            $this->subscribersQuery()->cursor()->each(function ($subscriber) use ($out) {
                fputcsv($out, [
                    $subscriber->email,
                    $subscriber->consent_at,
                    $subscriber->last_test_at,
                    $subscriber->test_count,
                    $subscriber->language,
                ]);
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Une ligne par adresse ayant coché la case au moins une fois.
     *
     * La date retenue est celle du premier consentement : c'est elle qui sert
     * de preuve en cas de contestation.
     */
    private function subscribersQuery(?string $search = null)
    {
        return Test::query()
            ->where('marketing_consent', true)
            ->when($search, fn ($q) => $q->where('visitor_email', 'ilike', '%' . $search . '%'))
            ->select(
                DB::raw('LOWER(visitor_email) as email'),
                DB::raw('MIN(marketing_consent_at) as consent_at'),
                DB::raw('MAX(created_at) as last_test_at'),
                DB::raw('COUNT(*) as test_count'),
                DB::raw('MAX(language) as language')
            )
            ->groupBy(DB::raw('LOWER(visitor_email)'))
            ->orderByDesc('consent_at');
    }
}
