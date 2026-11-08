<?php

namespace Tests\Unit\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\Rules\EnvOutsideConfig;
use Grafite\CognitiveLoad\Sloppy\Rules\FatController;
use Grafite\CognitiveLoad\Sloppy\Rules\FilterAfterFetch;
use Grafite\CognitiveLoad\Sloppy\Rules\PossibleNPlusOne;
use Grafite\CognitiveLoad\Sloppy\Rules\QueryInLoop;
use Grafite\CognitiveLoad\Sloppy\Rules\UnvalidatedMassAssignment;
use Tests\TestCase;

class LaravelRulesTest extends TestCase
{
    use ChecksRules;

    public function test_query_in_loop(): void
    {
        $findings = $this->check(new QueryInLoop, <<<'PHP'
            <?php
            namespace App;
            use App\Models\User;
            use Illuminate\Support\Facades\DB;
            use Illuminate\Support\Arr;
            class Sync {
                public function models($ids) { foreach ($ids as $id) { User::find($id); } }
                public function facade($ids) { foreach ($ids as $id) { DB::table('users')->where('id', $id)->first(); } }
                public function relation($users) { foreach ($users as $user) { $user->posts()->where('draft', true)->get(); } }
                public function nested($teams) { foreach ($teams as $team) { foreach ($team as $id) { User::find($id); } } }
                public function helpers($rows) { foreach ($rows as $row) { Arr::first($row); collect($row)->keys()->first(); $row->items()->first(); } }
                public function outside($ids) { $users = User::whereIn('id', $ids)->get(); foreach ($users as $user) { echo $user; } }
            }
            PHP);

        $this->assertSame(['App\Sync::models', 'App\Sync::facade', 'App\Sync::relation', 'App\Sync::nested'], $this->symbols($findings));
        $this->assertSame('low', $findings[2]->confidence);
    }

    public function test_filter_after_fetch(): void
    {
        $findings = $this->check(new FilterAfterFetch, <<<'PHP'
            <?php
            class Report {
                public function a() { return User::all()->where('active', true); }
                public function b() { return User::where('team', 1)->get()->count(); }
                public function c() { return User::where('active', true)->count(); }
                public function d($request) { return $request->all(); }
                public function e() { return Config::all()->filter(); }
                public function f($items) { return $items->get('key')->first(); }
            }
            PHP);

        $this->assertSame(['Report::a', 'Report::b'], $this->symbols($findings));
        $this->assertStringContainsString('->count() on the query', $findings[1]->advice);
    }

    public function test_possible_n_plus_one_requires_a_query_result_and_no_eager_loading(): void
    {
        $findings = $this->check(new PossibleNPlusOne, <<<'PHP'
            <?php
            class Report {
                public function a() { $posts = Post::where('live', true)->get(); foreach ($posts as $post) { echo $post->author->name; } }
                public function b() { foreach (Post::with('author')->get() as $post) { echo $post->author->name; } }
                public function c($nodes) { foreach ($nodes as $node) { echo $node->name->toString(); } }
                public function d() { foreach (Post::all() as $post) { $post->comments->count(); } }
            }
            PHP);

        $this->assertSame(['Report::a', 'Report::d'], $this->symbols($findings));
        $this->assertStringContainsString("with('author')", $findings[0]->advice);
    }

    public function test_env_outside_config(): void
    {
        $code = '<?php return ["key" => env("APP_KEY")];';

        $this->assertCount(1, $this->check(new EnvOutsideConfig, $code, 'app/Services/Keys.php'));
        $this->assertCount(0, $this->check(new EnvOutsideConfig, $code, 'config/app.php'));
        $this->assertCount(0, $this->check(new EnvOutsideConfig, $code, 'packages/billing/config/billing.php'));
    }

    public function test_fat_controller(): void
    {
        $actions = implode("\n", array_map(fn ($i) => "public function action{$i}() {}", range(1, 4)));

        $findings = $this->check(new FatController(['max_actions' => 3]), <<<PHP
            <?php
            namespace App\Http\Controllers;
            use Illuminate\Support\Facades\DB;
            class UserController extends Controller {
                {$actions}
                public function __construct() {}
                public static function routes() {}
                private function helper() { return DB::select('select 1'); }
            }
            class Lean extends Controller { public function __invoke() {} }
            abstract class BaseController { {$actions} }
            PHP);

        $messages = array_map(fn ($finding) => $finding->message, $findings);

        $this->assertSame([
            'Controller has 4 public actions (max 3).',
            'Controller queries the database directly through the DB facade.',
        ], $messages);
        $this->assertSame(['App\Http\Controllers\UserController', 'App\Http\Controllers\UserController'], $this->symbols($findings));
    }

    public function test_unvalidated_mass_assignment(): void
    {
        $findings = $this->check(new UnvalidatedMassAssignment, <<<'PHP'
            <?php
            class PostController {
                public function a($request) { return Post::create($request->all()); }
                public function b($post) { $post->update(request()->all()); }
                public function c($post) { $post->fill(Request::all())->save(); }
                public function d($request) { return Post::create($request->input()); }
                public function e($request) { return Post::create($request->validated()); }
                public function f($request) { return Post::create(['title' => $request->input('title')]); }
                public function g($request) { return Validator::make($request->all(), []); }
            }
            PHP);

        $this->assertSame(['PostController::a', 'PostController::b', 'PostController::c', 'PostController::d'], $this->symbols($findings));
    }
}
