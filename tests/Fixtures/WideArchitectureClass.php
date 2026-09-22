<?php

namespace Tests\Fixtures;

class WideArchitectureClass
{
    public function __construct(
        private readonly DependencyOne $one,
        private readonly DependencyTwo $two,
        private readonly DependencyThree $three,
        private readonly DependencyFour $four,
        private readonly DependencyFive $five,
        private readonly DependencySix $six,
        private readonly DependencySeven $seven,
        private readonly DependencyEight $eight,
    ) {}

    public function methodOne(): void {}

    public function methodTwo(): void {}

    public function methodThree(): void {}

    public function methodFour(): void {}

    public function methodFive(): void {}

    public function methodSix(): void {}

    public function methodSeven(): void {}

    public function methodEight(): void {}

    public function methodNine(): void {}

    public function methodTen(): void {}

    public function methodEleven(): void {}

    public function methodTwelve(): void {}
}
