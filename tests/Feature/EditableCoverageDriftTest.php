<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Keeps the storefront's editable coverage from decaying.
 *
 * The inline editor only sees regions someone marked, which means a region
 * nobody marked is indistinguishable from a region someone decided against.
 * There is no build step on the client side and no schema to catch it, so the
 * omission survives review and only surfaces when an admin goes looking for a
 * paragraph on a live site and cannot click it. This is the same job
 * PermissionRegistryDriftTest does for permission codes.
 *
 * Ways to make this test green, and they are all correct answers:
 *
 *     php artisan client:editable-audit --fix     plain headings, paragraphs, <img>
 *     wrap by hand as the report advises           CTAs, <div>/<span> copy, slots
 *     data-editable-ignore="<reason>"              static on purpose, and why
 *
 * Silencing it is not another way. A bare `data-editable-ignore` fails too, and
 * a region that nobody can edit and nobody declared static is the exact state
 * this test exists to report.
 */
class EditableCoverageDriftTest extends TestCase
{
    public function test_no_storefront_copy_is_left_unmarked(): void
    {
        $output = new BufferedOutput;
        $status = Artisan::call('client:editable-audit', [], $output);

        $this->assertSame(
            0,
            $status,
            "client:editable-audit báo có vùng chưa xử lý:\n\n".$output->fetch(),
        );
    }
}
