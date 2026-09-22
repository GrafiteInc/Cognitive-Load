<?php

namespace Tests\Fixtures;

class HighComplexityMethod
{
    private int $offset = 1;

    public function process(
        int $a,
        int $b,
        int $c,
        int $d,
        int $e,
        int $f,
        int $g,
        int $h,
        int $i,
        int $j,
        int $k,
        int $l
    ): int {
        $result = 0;
        $step1 = $a + $b;
        $step2 = $c + $d;
        $step3 = $e + $f;
        $step4 = $g + $h;
        $step5 = $i + $j;
        $step6 = $k + $l;

        if ($a > 0) {
            if ($b > 0) {
                if ($c > 0) {
                    if ($d > 0) {
                        if ($e > 0) {
                            if ($f > 0) {
                                $result = $step1 + $this->offset;
                            } else {
                                $result = $step2 + $this->offset;
                            }
                        } else {
                            $result = $step3 + $this->offset;
                        }
                    } else {
                        $result = $step4 + $this->offset;
                    }
                } else {
                    $result = $step5 + $this->offset;
                }
            } else {
                $result = $step6 + $this->offset;
            }
        } else {
            $result = $this->offset;
        }

        if ($result > 1000) {
            return $result;
        } elseif ($result > 500) {
            return $result - 10;
        } elseif ($result > 100) {
            return $result - 20;
        } elseif ($result > 50) {
            return $result - 30;
        } else {
            return 0;
        }
    }

    private function combine(int $x, int $y): int
    {
        return $x + $y;
    }
}
