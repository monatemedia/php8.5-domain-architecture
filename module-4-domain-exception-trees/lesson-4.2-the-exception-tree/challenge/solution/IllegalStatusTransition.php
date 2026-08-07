<?php
declare(strict_types=1);

namespace Workshop;

/**
 * INVARIANT I9: only the transitions in JobCardStatus::allowedNext() are legal.
 *
 * REFERENCE IMPLEMENTATION. The most-thrown exception in the domain — every
 * lifecycle method on JobCard routes through one private method that raises
 * this — and the best illustration of what structured context buys.
 *
 * THE OLD MESSAGE DID ALL THE WORK
 * ---------------------------------
 *     'Job card JC-1042 is In Progress and cannot become Invoiced. Legal from
 *      here: Awaiting Authorisation, Awaiting Parts, Completed, Cancelled.'
 *
 * Everything a caller could want is in there, and all of it is in English. A
 * client that wants to grey out the buttons for the illegal transitions has to
 * parse that sentence — and it will, and then somebody will improve the wording
 * and the client will break for reasons nobody connects to a copy edit.
 *
 * The same facts as fields survive rewording, translation, and a designer
 * shortening the string:
 *
 *     from      'in_progress'
 *     to        'invoiced'
 *     allowed   ['awaiting_authorisation', 'awaiting_parts', 'completed', 'cancelled']
 *
 * NOTE WHICH FORM OF THE STATUS GOES WHERE
 * -----------------------------------------
 * The context carries `->value` — the backed enum's stable string. The message
 * carries `->label()` — the human one. They are different on purpose:
 * `awaiting_authorisation` is for a machine, "Awaiting Authorisation" is for a
 * person, and mixing them means one audience is always being served badly.
 *
 * `allowed` is the field that makes this worth doing. A UI can build itself
 * from it. That is not a hypothetical: it is why an API returns structured
 * errors at all.
 */
final class IllegalStatusTransition extends JobCardException
{
    public static function from(
        JobCardId $id,
        JobCardStatus $current,
        JobCardStatus $attempted,
    ): self {
        $allowed = array_map(
            static fn(JobCardStatus $s): string => $s->value,
            $current->allowedNext()
        );

        return new self(
            $id,
            sprintf(
                'Job card %s is %s and cannot become %s. %s',
                $id,
                $current->label(),
                $attempted->label(),
                $current->isTerminal()
                    ? 'That state is terminal — nothing follows it.'
                    : 'Legal from here: ' . implode(', ', array_map(
                        static fn(JobCardStatus $s): string => $s->label(),
                        $current->allowedNext()
                    )) . '.'
            ),
            [
                'from'     => $current->value,
                'to'       => $attempted->value,
                'allowed'  => array_values($allowed),
                'terminal' => $current->isTerminal(),
            ]
        );
    }

    public function failureCode(): string
    {
        return 'illegal-status-transition';
    }
}
