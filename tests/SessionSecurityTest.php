<?php

declare(strict_types=1);

use Moves\Core\Auth;
use Moves\Core\Session;
use PHPUnit\Framework\TestCase;

final class SessionSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
        $_SESSION = [];
        $_ENV['APP_URL'] = 'https://moves.test';
        $_ENV['SESSION_SECURE'] = 'true';
        $_ENV['SESSION_HTTP_ONLY'] = 'true';
        $_ENV['SESSION_SAME_SITE'] = 'Strict';
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        session_id('');
        unset(
            $_ENV['APP_URL'],
            $_ENV['SESSION_SECURE'],
            $_ENV['SESSION_HTTP_ONLY'],
            $_ENV['SESSION_SAME_SITE'],
            $_ENV['SESSION_IDLE_TIMEOUT']
        );
    }

    public function testStartsSessionWithHardenedCookieParameters(): void
    {
        Session::start();

        $params = session_get_cookie_params();

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertTrue((bool) $params['secure']);
        self::assertTrue((bool) $params['httponly']);
        self::assertSame('Strict', $params['samesite']);
        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.use_only_cookies'));
    }

    public function testRegenerateChangesSessionIdentifierAndPreservesData(): void
    {
        Session::set('probe', 'kept');
        $before = session_id();

        Session::regenerate();

        self::assertNotSame('', $before);
        self::assertNotSame($before, session_id());
        self::assertSame('kept', Session::get('probe'));
    }

    public function testDestroyClearsSessionAndIdentifier(): void
    {
        Session::set('auth_user', 7);
        self::assertNotSame('', session_id());

        Session::destroy();

        self::assertSame('', session_id());
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
    }

    public function testInvalidSameSiteFallsBackToLax(): void
    {
        $_ENV['SESSION_SAME_SITE'] = 'None';

        Session::start();

        self::assertSame('Lax', session_get_cookie_params()['samesite']);
    }
    public function testAuthenticatedSessionExpiresAfterIdleTimeout(): void
    {
        $_ENV['SESSION_IDLE_TIMEOUT'] = '60';
        Session::set('auth_user', 7);
        Session::set('auth_last_activity', 1000);

        self::assertTrue(Auth::sessionIsFresh(1060));
        self::assertFalse(Auth::sessionIsFresh(1061));
    }

    public function testAuthenticatedSessionWithoutActivityTimestampFailsClosed(): void
    {
        Session::set('auth_user', 7);

        self::assertFalse(Auth::sessionIsFresh(1000));
    }

    public function testFutureActivityTimestampFailsClosed(): void
    {
        Session::set('auth_user', 7);
        Session::set('auth_last_activity', 1001);

        self::assertFalse(Auth::sessionIsFresh(1000));
    }

    public function testInvalidIdleTimeoutFallsBackToThirtyMinutes(): void
    {
        $_ENV['SESSION_IDLE_TIMEOUT'] = '0';
        Session::set('auth_user', 7);
        Session::set('auth_last_activity', 1000);

        self::assertTrue(Auth::sessionIsFresh(2800));
        self::assertFalse(Auth::sessionIsFresh(2801));
    }

}
