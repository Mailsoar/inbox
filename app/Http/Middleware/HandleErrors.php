<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

class HandleErrors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
            
            // Si c'est une erreur 419, la transformer en réponse JSON avec code 200
            if ($response->getStatusCode() === 419) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'CSRF token mismatch',
                        'message' => 'Votre session a expiré. Veuillez rafraîchir la page.',
                        'code' => 419
                    ], 200);
                }
                
                // Pour les requêtes normales, rediriger
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Votre session a expiré. Veuillez réessayer.');
            }
            
            return $response;
        } catch (TokenMismatchException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'CSRF token mismatch',
                    'message' => 'Votre session a expiré. Veuillez rafraîchir la page.',
                    'code' => 419
                ], 200);
            }
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Votre session a expiré. Veuillez réessayer.');
        } catch (\Exception $e) {
            // Logger l'erreur
            \Log::error('Unhandled exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'url' => $request->fullUrl()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Server error',
                    'message' => 'Une erreur est survenue',
                    'code' => 500
                ], 200);
            }
            
            return redirect()
                ->back()
                ->with('error', 'Une erreur est survenue. Veuillez réessayer.');
        }
    }
}