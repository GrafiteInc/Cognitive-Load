<?php

namespace Tests\SloppyFixtures\Http\Controllers;

use Illuminate\Support\Facades\DB;

class OrderController
{
    public function index(): array
    {
        return DB::table('orders')->get()->all();
    }

    public function create(): void {}

    public function store(): void {}

    public function show(): void {}

    public function edit(): void {}

    public function update(): void {}

    public function destroy(): void {}

    public function export(): void {}
}
