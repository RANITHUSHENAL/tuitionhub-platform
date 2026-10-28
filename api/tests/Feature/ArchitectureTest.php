<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * D-011 / D-015: RoleAssignmentService is the only writer of user_roles.
 * A source scan, so a new writer anywhere in app/ fails this test.
 */
class ArchitectureTest extends TestCase
{
    private const WRITER = 'Services/RoleAssignmentService.php';

    /** Files allowed to mention UserRole / user_roles at all (reads and relationships). */
    private const ALLOWED = [
        self::WRITER,
        'Models/UserRole.php',
        'Models/User.php',
        'Models/Role.php',
        'Models/Institute.php',
        'Authorization/TenantAccess.php',
        'Http/Presenters/AuthSessionPresenter.php',
        'Providers/AppServiceProvider.php',
    ];

    private const WRITE_PATTERNS = [
        '/new\s+UserRole\b/',
        '/UserRole::(create|forceCreate|insert|insertGetId|insertOrIgnore|upsert|updateOrCreate|firstOrCreate|destroy)\b/',
        "/table\(\s*['\"]user_roles['\"]\s*\)/",
        '/->(roles|userRoles)\(\)\s*->\s*(create|forceCreate|save|saveMany|createMany|insert|update|delete)\b/',
    ];

    public function test_only_role_assignment_service_writes_user_roles(): void
    {
        $app = dirname(__DIR__, 2).'/app/';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($app)));
            $source = $this->codeWithoutComments(file_get_contents($file->getPathname()));

            if (preg_match('/\bUserRole\b|user_roles/', $source) !== 1) {
                continue;
            }

            $this->assertContains($path, self::ALLOWED, "{$path} references user_roles; only RoleAssignmentService may write it.");

            if ($path === self::WRITER) {
                continue;
            }

            foreach (self::WRITE_PATTERNS as $pattern) {
                $this->assertSame(0, preg_match($pattern, $source), "{$path} writes user_roles ({$pattern}).");
            }
        }
    }

    /**
     * Quiet writes skip model events, which carry the UUID-immutability and generated-column guards
     * (D-017). They are not allowed anywhere in app/.
     */
    public function test_no_quiet_model_writes_in_app(): void
    {
        $app = dirname(__DIR__, 2).'/app/';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app));
        $checked = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($app)));
            $source = $this->codeWithoutComments(file_get_contents($file->getPathname()));
            $checked++;

            $this->assertSame(
                0,
                preg_match('/\b(saveQuietly|updateQuietly|withoutEvents)\b/', $source),
                "{$path} uses a quiet model write (saveQuietly, updateQuietly or withoutEvents).",
            );
        }

        $this->assertGreaterThan(0, $checked);
    }

    /**
     * Comments may mention user_roles (for example, lock-order notes); only code counts.
     */
    private function codeWithoutComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }
}
