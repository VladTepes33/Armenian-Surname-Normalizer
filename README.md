# Armenian-Surname-Normalizer
> PHP library for phonetic normalization of Armenian surnames transcribed in the Latin alphabet, designed as a component of a fuzzy-search system for Armenian diaspora archives.
The goal is to group orthographic variants of the same surname under a single canonical form, enabling similarity-based search across corpora of primary-source records.
---

## The Problem

In the context of Armenian names drawn from archival records and civil registries across Europe, the challenge is amplified by inconsistent transliteration practices. 
The same Armenian name may appear in multiple forms depending on the country, language, or even the clerk who recorded it—using French, German, Russian, or other Latin-based conventions. Diacritics may be added or omitted, phonetic approximations vary, and spellings shift over time.

As a result, standard SQL LIKE or SOUNDEX queries miss many valid matches, since they rely on surface-level similarity rather than deeper phonetic or structural equivalence. At the same time, n-gram–based full-text search mechanisms or more advanced engines like Lucene and ElasticSearch which are better suited to capturing these variations, are rarely available natively on shared hosting platforms.

This project is designed to bridge that gap by introducing a normalization and matching approach tailored to these historical and linguistic inconsistencies, 
making it possible to retrieve relevant records despite significant spelling variation.





| Raw Input | Normalized |
|---|---|
| `Hagopian` | `agopian` |
| `Der Hagopian` | `agopian` |
| `Hagopyan` | `agopian` |
| `Petrossian` | `petrosian` |
| `Petrosian` | `petrosian` |
| `Krikorian` | `krikorian` |
| `Grigorian` | `grigorian` (g→k only in initial position before a/o/u) |
| `Terzibachian` | `terzibakian` |
| `Terzibajian` | `terzibakian` |
| `Frenkian` | `frenkian` |
| `Frenghian` | `frenkian` |
| `Manoogian` | `manukian` |
| `Manouchian` | `manukian` |

---

## How It Works

Normalization runs through 10 sequential steps:

```
Raw string
  │
  ▼ Step 1 — Lowercase + remove accents (é→e, ç→c …)
  ▼ Step 2 — Nasal clusters (ngui, ngu, ng → nk)
  ▼ Step 3 — Phonetic digraphs (tch→j, ch→ch, kh→k, gh→g, ph→f, ck→k …)
  ▼ Step 4 — sh/zh simplification (currently a no-op, reserved)
  ▼ Step 5 — Vowel digraphs (ou→u, oo→u, eu→e, ei→e …)
  ▼ Step 6 — Deduplicate consecutive identical characters (tt→t, ll→l …)
  ▼ Step 7 — Strip honorific prefixes (Der, Ter, Hadji, Ben … )
  ▼ Step 8 — Normalize Armenian suffixes (→ canonical -ian)
  ▼ Step 9 — Final phonetic adjustments (g/k→k initial, h-drop, y/j→i …)
  ▼ Step 10 — Strip separator characters
  │
  ▼ Normalized form
```

### Step Details

**Step 2 — Nasals**

`ngui / ngi / ngu / ngh / ng / nnk / nkk` → `nk`

Handles the French transcription habit of writing the /ŋk/ cluster as ng.

**Step 3 — Phonetic digraphs**

| Input | Output | Notes |
|---|---|---|
| `tch` | `j` | French tch = /tʃ/ |
| `dj` | `j` | /dʒ/ → merged with /tʃ/ |
| `dz` | `ts` | |
| `tz` | `ts` | |
| `ch` | `ch` | kept |
| `kh` | `k` | Armenian խ |
| `gh` | `g` | Armenian ղ |
| `ph` | `f` | |
| `th` | `t` | |
| `ck` | `k` | English spelling |

> **Note:** `_step3_phonetic_digraphs` (maps `ch→k`) exists in the source but is **not called**. The active function is `_step31_phonetic_digraphs` (maps `ch→ch`). This is intentional: keeping `ch` distinct improves precision for names like *Katchadourian*.

**Step 7 — Prefixes**

Honorific prefixes are removed unconditionally (with separator): `der`, `ter`, `hadji`, `haci`, `ben`, `melik`. 

**Step 8 — Suffixes**

The canonical Armenian patronymic suffix is `-ian`. The following variants are all mapped to it:

`-ounian`, `-unian`, `-iantz`, `-yantz`, `-iants`, `-yants`, `-ients`, `-ientc`, `-iadis`, `-iades`, `-ianc`, `-ians`, `-iane`, `-iene`, `-iens`, `-yant`, `-entz`, `-entc`, `-ouni`, `-yan`, `-ean`, `-jan`

**Step 9 — Final adjustments**

- `guian / gian` → `kian`
- `ok / okian` → `uk / ukian` (vowel harmony before k)
- `[gjk]ien` or `[gjk]ian` → `kian`
- `chian` → `kian`
- `iii / ii / ie / y` → `i`
- Initial `h` dropped (silent in many transcriptions)
- Initial `g` or `k` before `a/o/u` → `k`

---

## Usage

```php
require 'src/ArmenianNormalizer_v1.php';

echo normalize_armenian_v1('Hagopian');       // → agopian
echo normalize_armenian_v1('Der Hagopian');   // → agopian
echo normalize_armenian_v1('Hagopyan');       // → agopian
echo normalize_armenian_v1('Frenghian');    // → frenkian
```

---

## Search System Architecture

Normalization is one component of a larger pipeline stored in MariaDB.

### Indexing vs. Search

```
Raw name (indexing)              Raw name (search)
  │                                │
  ▼ normalize_armenian_v1()        ▼ normalize_armenian_v1()
  ▼ Split into trigrams            ▼ Split into trigrams
  ▼ Store in MariaDB               ▼ Compute proximity score
                                   ▼ Ranked results
```

### Proximity Score

```
score = (shared trigrams / total trigrams)
      + exact_name_bonus       # +1.5 if input name == stored name
      + canonical_bonus        # +1.0 if canonical forms are identical
      + first_trigram_bonus    # +0.5 if first trigram matches
```

The cutoff threshold is intentionally permissive to maximize recall. The bonuses serve to push strongly matching results away from the threshold rather than to inflate weak ones.


## Live Deployment

This library is deployed at [alexvladesco.fr/us/search.php](https://alexvladesco.fr/us/search.php),
where it powers the Armenian surname search across digitized diaspora archives.

The next version will incorporate nominal root analysis during canonical form generation.

### Corpus

| Collection | Source |
|---|---|
| French diaspora | INSEE data (births, deaths) |
| War orphans Archives | registers of the Karen Jeppe Aleppo Rescue Home (1921–1923), now held at the United Nations Archives in Geneva |

---

## Limitations & Known Issues

- **`Krikorian` ≠ `Grigorian`:** the `g/k → k` rule only applies in initial position before `a/o/u`. The internal `g` in `Grigorian` is preserved → `krigorian`. Merging these two families would require extending the rule to internal velar stops.
- **Step 4 is a no-op:** the `sh/zh` simplification is commented out. `Shahinian` therefore retains its `sh`. Enable if the corpus conflates sh and s.
- **`_step3` is dead code:** only `_step31` is called. The `ch→k` variant in `_step3` has been superseded — remove it to avoid confusion.
- **No transliteration from the Armenian alphabet:** input must already be in the Latin alphabet.
- **Western vs. Eastern Armenian:** no distinction is made; normalization is purely orthographic.
- **Order sensitivity:** steps are not individually idempotent — running them out of order produces incorrect results.

---

## Files

```
armenian-normalizer/
├── src/
│   └── ArmenianNormalizer.php   # All normalization functions
├── php/
│   └── test_surnames.php
└── README.md
```

---

## License

Research prototype — no warranty. You are free to use, modify, and share this code for genealogical, academic, or any other purposes, provided that proper credit is given to the original author.
