<?php
declare(strict_types=1);

/**
 * Example 02 — One word, three contexts
 * ======================================
 *
 * Three departments. One word. Three genuinely different concepts, each of
 * which is complete and correct for the department that holds it.
 *
 * Namespaces are used here for exactly the reason they exist: the same class
 * name meaning different things in different places is not a collision to be
 * resolved, it is a boundary to be respected.
 *
 * Notice what each model CANNOT do. Accounts\Vehicle has no odometer and that
 * is not an omission — accounts has no business knowing the mileage, and a
 * model that cannot express something it must never do is a model that cannot
 * do it by accident.
 *
 *     php 02-one-word-three-contexts.php
 */

namespace {
    function section(string $title): void
    {
        echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
    }
}


// ═══════════════════════════════════════════════════════════════════════════
namespace Workshop {

    /**
     * To the workshop, a vehicle is a thing being worked on, in a bay, with a
     * history and a set of open faults.
     */
    final class Vehicle
    {
        /** @var list<string> */
        private array $openFaults = [];

        public function __construct(
            public readonly string $registration,
            private int            $odometer,
            private ?string        $bay = null,
        ) {}

        public function recordOdometer(int $reading): void
        {
            if ($reading < $this->odometer) {
                throw new \DomainException(
                    "Odometer cannot decrease: {$this->odometer} -> {$reading}."
                );
            }
            $this->odometer = $reading;
        }

        public function reportFault(string $fault): void
        {
            $this->openFaults[] = $fault;
        }

        public function moveToBay(string $bay): void
        {
            $this->bay = $bay;
        }

        public function summary(): string
        {
            return sprintf(
                '%s | %s km | bay %s | %d open fault(s)',
                $this->registration,
                number_format($this->odometer),
                $this->bay ?? '-',
                count($this->openFaults)
            );
        }
    }
}


// ═══════════════════════════════════════════════════════════════════════════
namespace Accounts {

    /**
     * To accounts, a vehicle is a registration string that appears on an
     * invoice. That is genuinely all it is.
     *
     * There is no odometer here and there should not be. Accounts has no
     * business knowing the mileage, and a model with no way to express it is a
     * model that cannot leak it.
     */
    final readonly class Vehicle
    {
        public function __construct(public string $registration) {}

        public function invoiceLine(): string
        {
            return "Work carried out on vehicle {$this->registration}";
        }
    }
}


// ═══════════════════════════════════════════════════════════════════════════
namespace Parts {

    /**
     * To the parts desk, a vehicle is a compatibility key. Two vehicles with
     * different registrations but the same engine code and model year are, for
     * this context's purposes, THE SAME VEHICLE — which is a sentence that would
     * be nonsense in either of the other two contexts.
     */
    final readonly class Vehicle
    {
        public function __construct(
            public string $engineCode,
            public int    $modelYear,
            public string $trim,
        ) {}

        public function fitmentKey(): string
        {
            return strtoupper("{$this->engineCode}-{$this->modelYear}-{$this->trim}");
        }

        public function isCompatibleWith(self $other): bool
        {
            return $this->fitmentKey() === $other->fitmentKey();
        }
    }
}


// ═══════════════════════════════════════════════════════════════════════════
namespace {

    section('Three models, one word');

    $workshopVehicle = new \Workshop\Vehicle('AB12CDE', 84_500, 'Bay 3');
    $workshopVehicle->reportFault('Rear brake discs below minimum thickness');
    $workshopVehicle->reportFault('Nearside indicator intermittent');
    echo "  Workshop:  " . $workshopVehicle->summary() . "\n";

    $accountsVehicle = new \Accounts\Vehicle('AB12CDE');
    echo "  Accounts:  " . $accountsVehicle->invoiceLine() . "\n";

    $partsVehicle = new \Parts\Vehicle('K9K', 2019, 'Sport');
    echo "  Parts:     fitment key " . $partsVehicle->fitmentKey() . "\n";


    section('What each model refuses to do');

    $refusals = [
        'Workshop: odometer may not decrease' => static function () use ($workshopVehicle): void {
            $workshopVehicle->recordOdometer(80_000);
        },
        'Accounts: has no odometer at all' => static function () use ($accountsVehicle): void {
            /** @phpstan-ignore-next-line — deliberately calling a method that does not exist */
            $accountsVehicle->recordOdometer(90_000);
        },
        'Parts: is readonly — a fitment key cannot drift' => static function () use ($partsVehicle): void {
            $partsVehicle->modelYear = 2020;
        },
    ];

    foreach ($refusals as $label => $attempt) {
        try {
            $attempt();
            echo "  [!!] {$label} — did not refuse.\n";
        } catch (\Throwable $e) {
            echo "  [ok] {$label}\n";
            echo "       " . $e::class . ': ' . $e->getMessage() . "\n";
        }
    }

    echo "\n  Two of those three failures are an Error rather than an exception,\n";
    echo "  which is the strongest kind of guarantee there is: the concept does\n";
    echo "  not exist in that context, so the mistake is not merely refused at\n";
    echo "  runtime — there is nothing there to call.\n";

    echo "\n  And a fitment comparison, which only Parts has a word for:\n";
    $comfort = new \Parts\Vehicle('K9K', 2019, 'Comfort');
    printf(
        "    %s vs %s -> %s\n",
        $partsVehicle->fitmentKey(),
        $comfort->fitmentKey(),
        $partsVehicle->isCompatibleWith($comfort) ? 'compatible' : 'not compatible'
    );


    section('How they talk to each other');

    /**
     * The translation happens at the boundary, by an identifier both sides
     * agree on. Neither model learns anything about the other.
     */
    function toAccounts(\Workshop\Vehicle $v): \Accounts\Vehicle
    {
        return new \Accounts\Vehicle($v->registration);
    }

    $translated = toAccounts($workshopVehicle);
    echo "  Workshop -> Accounts:  " . $translated->invoiceLine() . "\n";
    echo "\n  One function, three lines, no shared base class, no shared package.\n";
    echo "  If accounts changes what it needs, that function changes and nothing\n";
    echo "  in the workshop model moves.\n";


    section('The instinct to resist');

    echo "  Looking at three classes called Vehicle, the trained instinct says\n";
    echo "  \"this is duplication, unify them\". Resist it.\n\n";
    echo "  They share a spelling, not a concept. Unifying them produces a class\n";
    echo "  with every field all three contexts need, most of them nullable,\n";
    echo "  which means no invariant can be enforced on any of them.\n\n";
    echo "  Example 03 runs that unification step by step and counts the damage.\n\n";
}
