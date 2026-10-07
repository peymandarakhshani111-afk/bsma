# Job: proof-read Persian articles (bsma.ir) — write one JSON patch per article

Work ONLY with files in this folder (`landing/seo/work/`). Do not touch the website, do not edit any other repo file.

* `todo-N.txt` — the article ids assigned to YOU (N is given in your task; one id per line). Skip an id if `patches/<id>.json` already exists.
* `view/<id>.txt` — compact view of the article (text between tags is byte-exact; `<a>` = link; `[[IMG …]]` = image; `[[EXISTING-AD …]]` = product ad already in the article).
* `catalog.txt` — `id | product | category` (only products you may advertise).
* Output: `patches/<id>.json` (UTF-8 JSON). Full rules for the schema are in `../ARTICLE_EDITOR_PROMPT.md` — read it once.

## Be token-frugal (this matters)
* Read each view file ONCE, think briefly, write its patch immediately, move on. No summaries, no commentary, no re-reading.
* Read `catalog.txt` once at the start.
* Do not print file contents to the terminal; use the Read tool.
* Target: ≤ 25 fixes per article (only REAL errors: spelling, punctuation, missing half-space, broken/unclear sentences, doubled words, clearly wrong facts). Clean article → `"fixes": []`.
* `flags`: ≤ 4 short notes per article (dubious claims, copied/competitor/promo leftovers, structure problems).
* `callouts`: ≤ 1 per article. `ad`: add at most ONE new in-body ad, only if a catalog product is directly relevant to a section; otherwise `"new": null`.

## Patch schema (exact)
```json
{"id": 13100,
 "fixes": [{"old": "exact text copied from the view, inside ONE text segment, no < > [[ ]]", "new": "corrected", "kind": "typo|grammar|punct|wording|fact"}],
 "callouts": [{"para_starts": "first 15-40 chars of a whole <p> paragraph", "type": "note|tip|warn|key"}],
 "ad": {"existing": "keep|replace|remove|none", "new": null},
 "flags": ["short note"]}
```
`ad.new` when used: `{"products":[ids from catalog], "section_heading":"exact heading text or TOP", "title":"≤7 words", "text":"20-40 words fluent Persian, no invented specs/numbers", "cta":"مشاهده محصول|اطلاعات بیشتر|خرید محصول|دریافت مشاوره"}`.
Keep digits unchanged in fixes (except kind=fact). Never alter brand names, model numbers, standard codes.

## Saving progress (important — usage limits can interrupt you)
After every 4 articles run:
`git add landing/seo/work/patches && git commit -m "editor patches" && git push origin HEAD:claude/article-editor-patches-N   (N = your number)`
If a push fails, retry once; if you hit a usage/rate limit, stop immediately (everything already pushed is kept).
When `todo.txt` is exhausted, push a last time and reply with the single word `DONE`.
