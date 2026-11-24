# Changelog

## 0.3.0

- **A service that fails no longer vanishes.** Its doors stay for whoever had them, with their logo, saying since when the
  service does not answer, with a question mark instead of the number of alerts, and with a line in «To sort out», always
  in sight: nothing waiting and not knowing are not the same. The block keeps, of whoever runs something, the name and the
  address of their doors and when each service last answered, as preferences of theirs.
- The contract: a function that fails lets its exception go instead of returning `[]`. `board::ask()` gives the services
  that did not answer, for an app that shows the same panel.

## 0.2.2

- The name of each service in «To sort out» is read whole also on a wide screen: its column is as wide as the longest name
  in the list, and past the widest it allows the name wraps to a second line instead of being cut.

## 0.2.1

- The setting of the services is written when Moodle applies its default, so it no longer shows as a new setting on every upgrade of any plugin.

## 0.2.0

- **One panel, whichever services and however many.** «To sort out»: the alerts of every service in one list, the urgent
  first, then by when they are due (`due`, new in the contract), five in sight and the rest behind «and N more»; each
  line says its service, with its logo. «Your services»: a door into each, all the same size, with how many alerts it has;
  one wide door, a row of up to four, rows of four, or compact doors when there are many.
- **A fixed order**, never the one the plugins answer in: the site's, set in the block's settings with the services to
  hide; the list fills itself with what is installed.
- **Each door carries the logo of its product**, its plugin's own icon, in its colours; the block's neutral one when it
  has none.
- The block knows no product, and the README is the contract any new one follows, now version 2.
- **The art direction's design**: the urgent with a red disc and a soft veil, those of today with an amber clock, each
  line straight to «Sort it out»; doors with the product's logo on a plate that stays light also in dark mode, the figures
  of the state in bold, an arrow by the name, and how many alerts it has; compact doors without the shortcuts.

## 0.1.1

- At the top and across the whole width also on a dashboard whose theme lays its blocks out in columns.

## 0.1.0

- First version: what is waiting to be sorted out across every service, the urgent first, and a door into each; the
  contract any plugin uses to take part (`<component>_oksigenia_work`); on install, at the top of every dashboard; and
  «My courses» out of the main bar for whoever has no course. A plain look until the art direction's design.
