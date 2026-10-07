# Job (pass 2): careful Persian proof-reading of articles — write one JSON patch per article

These articles were already cleaned mechanically and only lightly reviewed. Do a careful, thorough pass like a professional Persian technical editor.
Work ONLY with files in this folder (`landing/seo/work2/`). Do not touch the website or any other repo file.

* `todo-N.txt` — the article ids assigned to YOU (N is in your task; one id per line). Skip an id whose `patches/<id>.json` already exists.
* `view/<id>.txt` — compact view of an article (text between tags is byte-exact; `<a>` = link; `[[IMG …]]` = image; `[[EXISTING-AD …]]` = a product ad already in the article).
* `catalog.txt` — `id | product | category` (the only products you may advertise).
* Output: `patches/<id>.json`. Read `../ARTICLE_EDITOR_PROMPT.md` once for the full rules (fixes / callouts / ad / flags) and follow it.

## Token frugality
Read `catalog.txt` and the prompt once. For each article: read its view once, think briefly, write the patch immediately, move on. No commentary, no re-reading, no printing of files to the terminal.

## What good looks like
* `fixes`: every REAL error — typos, wrong/missing punctuation, spacing around punctuation, missing half-spaces (ZWNJ), unclear/incomplete sentences, doubled words, broken grammar, clearly wrong facts (kind `fact` is allowed here when you are certain). Typical article: 8–30 fixes; a clean one may have 0. `old` must be copied exactly from the view, lie inside ONE text segment (no `<` `>` `[[ ]]`, never across `<strong>/<a>/<li>`), and be unique (25–120 chars with neighbouring words).
* `callouts`: 0–2 whole `<p>` paragraphs worth highlighting (warn / key / note / tip).
* `ad`: `existing` keep|replace|remove|none; add ONE in-body ad (`new`) only when a catalog product is DIRECTLY relevant to a section (title ≤7 words, text 20–40 fluent Persian words, no invented specs). Total ≤2 ads per article.
* `flags`: ≤5 short notes for things a human must decide (dubious claims, outdated data, copied/competitor/promo leftovers, structure problems).

## Saving progress (usage limits can interrupt you)
After every 3 articles: `git add landing/seo/work2/patches && git commit -m "editor2 patches" && git push origin HEAD:claude/article-editor2-N`  (N = your number; retry once on failure).
If you hit a usage/rate limit, stop immediately. When your list is finished, push once more and reply with the single word `DONE`.
