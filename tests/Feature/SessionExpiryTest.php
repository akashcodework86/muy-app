<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    public function test_phase3_uses_an_isolated_session_cookie(): void
    {
        $this->assertSame('muy_phase3_session', config('session.cookie'));
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_html_419_redirects_to_login_instead_of_showing_page_expired(): void
    {
        $request = Request::create('/logout', 'POST');
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);

        $response = app(ExceptionHandler::class)->render(
            $request,
            new HttpException(419, 'Page Expired'),
        );

        $this->assertTrue($response->isRedirect(route('login')));
        $this->assertNotSame(419, $response->getStatusCode());
    }
}
