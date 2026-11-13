<?php

namespace Tests\Unit;

use App\Services\Seating\SeatingLayout;
use App\Services\Seating\SeatingPlanner;
use PHPUnit\Framework\TestCase;

class SeatingLayoutTest extends TestCase
{
    public function test_straight_line_order_matches_intan(): void
    {
        $this->assertSame([1], SeatingLayout::lineOrder(1));
        $this->assertSame([1, 2], SeatingLayout::lineOrder(2));
        $this->assertSame([2, 1, 3], SeatingLayout::lineOrder(3));
        $this->assertSame([3, 1, 2, 4], SeatingLayout::lineOrder(4));
        $this->assertSame([4, 2, 1, 3, 5], SeatingLayout::lineOrder(5));
        $this->assertSame([5, 3, 1, 2, 4, 6], SeatingLayout::lineOrder(6));
        $this->assertSame([8, 6, 4, 2, 1, 3, 5, 7, 9], SeatingLayout::lineOrder(9));
        $this->assertSame([7, 5, 3, 1, 2, 4, 6, 8], SeatingLayout::lineOrder(8));
    }

    private function bySeq(array $layout): array
    {
        $seats = [];
        foreach ($layout['seats'] as $s) {
            $seats[$s['seq']] = $s;
        }
        ksort($seats);

        return $seats;
    }

    public function test_every_layout_has_one_seat_per_number(): void
    {
        foreach ([SeatingLayout::LINE, SeatingLayout::U_SHAPE, SeatingLayout::ROUND] as $layout) {
            $config = ['rows' => 2, 'per_row' => 5, 'head' => 3, 'arm' => 4, 'tables' => 4, 'per_table' => 9];
            $built = SeatingLayout::build($layout, $config);
            $seqs = array_column($built['seats'], 'seq');
            sort($seqs);

            $this->assertSame(range(1, SeatingLayout::seatCount($layout, $config)), $seqs, $layout);
        }
    }

    public function test_u_shape_arms_alternate_starting_on_the_chairs_right(): void
    {
        $seats = $this->bySeq(SeatingLayout::build(SeatingLayout::U_SHAPE, ['head' => 3, 'arm' => 3]));

        $this->assertTrue($seats[2]['x'] < $seats[1]['x'] && $seats[1]['x'] < $seats[3]['x']);
        $this->assertTrue($seats[4]['x'] < $seats[1]['x'], 'seat 4 on the chair\'s right arm (audience left)');
        $this->assertTrue($seats[5]['x'] > $seats[1]['x']);
        $this->assertSame($seats[4]['y'], $seats[5]['y']);
        $this->assertTrue($seats[6]['y'] > $seats[4]['y']);
    }

    public function test_round_main_table_starts_top_left_and_other_tables_face_it(): void
    {
        $seats = $this->bySeq(SeatingLayout::build(SeatingLayout::ROUND, ['tables' => 3, 'per_table' => 9]));

        $this->assertTrue($seats[1]['x'] < $seats[2]['x'] && $seats[1]['y'] === $seats[2]['y']);
        $this->assertTrue($seats[3]['y'] > $seats[1]['y'], 'main table numbers go downwards');
        $this->assertTrue($seats[9]['y'] > $seats[7]['y'], 'odd last seat at the bottom');

        $this->assertSame(1, $seats[10]['table']);
        $this->assertTrue($seats[12]['y'] < $seats[10]['y'], 'other tables start at the side facing the main table');
        $this->assertTrue($seats[10]['x'] < $seats[19]['x'], 'table 1 is on the main table\'s right (audience left)');
    }

    public function test_fit_config_gives_enough_seats(): void
    {
        foreach ([SeatingLayout::LINE, SeatingLayout::U_SHAPE, SeatingLayout::ROUND] as $layout) {
            foreach ([1, 7, 18, 61] as $people) {
                $this->assertGreaterThanOrEqual($people, SeatingLayout::seatCount($layout, SeatingLayout::fitConfig($layout, $people)));
            }
        }
    }

    private function planner(): SeatingPlanner
    {
        $p = new SeatingPlanner(['layout' => SeatingLayout::LINE, 'config' => ['rows' => 1, 'per_row' => 5]]);
        foreach ([['a', 'KSU', 300], ['b', 'Menteri', 100], ['c', 'Pengarah', 500], ['d', 'TM', 200]] as [$key, $name, $sort]) {
            $p->addPerson(SeatingPlanner::person(['key' => $key, 'name' => $name, 'rank_sort' => $sort]));
        }

        return $p;
    }

    public function test_people_are_ordered_by_rank_and_chair_and_host_take_seats_1_and_2(): void
    {
        $p = $this->planner();
        $this->assertSame(['b', 'd', 'a', 'c'], $p->state['order']);

        $p->state['chair'] = 'a';
        $p->state['host'] = 'c';
        $p->arrange();

        $this->assertSame(['1' => 'a', '2' => 'c', '3' => 'b', '4' => 'd'], $p->state['seats']);
        $this->assertSame([], $p->misplaced());
    }

    public function test_swap_flags_misplacement_and_absent_people_reflow(): void
    {
        $p = $this->planner();
        $p->arrange();
        $p->swap(1, 4);

        $this->assertSame('c', $p->state['seats']['1']);
        $this->assertContains(1, $p->misplaced());

        $p->setPresent('b', false);
        $this->assertSame(['1' => 'd', '2' => 'a', '3' => 'c'], $p->state['seats']);
        $this->assertSame(['b'], $p->absent());
    }

    public function test_replace_puts_the_stand_in_in_the_same_seat_and_rank(): void
    {
        $p = $this->planner();
        $p->state['chair'] = 'b';
        $p->arrange();
        $stand = $p->addPerson(SeatingPlanner::person(['key' => 'x', 'name' => 'Stand-in', 'present' => false]));

        $p->replace(1, $stand);

        $this->assertSame('x', $p->state['seats']['1']);
        $this->assertSame('x', $p->state['chair']);
        $this->assertFalse($p->state['people']['b']['present']);
        $this->assertSame(['b', 'x', 'd', 'a', 'c'], $p->state['order']);
    }

    public function test_moving_in_the_running_order_reflows_seats(): void
    {
        $p = $this->planner();
        $p->arrange();
        $p->moveInOrder('c', 'b');

        $this->assertSame(['c', 'b', 'd', 'a'], $p->state['order']);
        $this->assertSame('c', $p->state['seats']['1']);
    }

    public function test_more_people_than_seats_leaves_the_rest_unseated(): void
    {
        $p = $this->planner();
        $p->state['config']['per_row'] = 2;
        $p->arrange();

        $this->assertCount(2, $p->state['seats']);
        $this->assertSame(['a', 'c'], $p->unseated());
    }
}
