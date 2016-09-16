Email Spool Manager
-------------------

Nut command line spool management:

```
Usage:
  php app/nut email:spool [options]

Options:
      --clear           Clear all un-sent message files from the queue. USE WITH CAUTION!
      --flush           Flush (send) any queued emails.
      --recover         Attempt to restore any incomplete email to a valid state.
      --show            Show any queued emails.
```
