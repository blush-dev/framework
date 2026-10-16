# Progress and meters

Two built-in [blocks](README.md) show a number as a bar:

```markdown
::progress[Reading challenge]{value=12 max=50}

::meter[Battery]{value=62 low=20 high=80 optimum=100}
```

| Block  | Use it for                                                             | Props                                                                                                                                                 |
|------------|------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|
| `progress` | How far along something is: a goal, a series, a task                   | `value`, and `max` (100 by default, so `value` alone is a percentage). Leave out `value` for a bar that shows work in progress without an amount.     |
| `meter`    | A measurement within a range: a score, a rating, how full something is | `value`, `min` (0) and `max` (100), and optionally `low`, `high`, and `optimum`, which mark the range's poor and good parts so browsers can color it. |

The label names the bar for screen readers and is shown beside it, and
the value is shown as text too: "24%" out of 100, or "12 of 50".
Values outside the range are kept at its ends.
