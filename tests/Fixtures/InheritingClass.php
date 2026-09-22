<?php

namespace Tests\Fixtures;

class InheritingClass extends BaseFixtureClass
{
    public function name(): string
    {
        return 'inheriting';
    }
}
