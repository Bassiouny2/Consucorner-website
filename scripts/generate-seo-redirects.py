#!/usr/bin/env python3
"""
Generate SEO redirect CSV mapping old ConsuCorner category URLs to new site URLs.
"""
from __future__ import annotations

import re
from pathlib import Path
from urllib.parse import urlparse

import pandas as pd

OLD_XLS = Path(r"c:\Users\DELL\Downloads\categories seo - old website.xls")
NEW_CAT_CSV = Path(r"c:\Users\DELL\Downloads\category seo - new website .csv")
NEW_SPEC_CSV = Path(r"c:\Users\DELL\Downloads\spcialty seo - new website.csv")
OUTPUT_CSV = Path(r"c:\Users\DELL\Downloads\consucorner-rankmath-redirections-import.csv")
HUMAN_CSV = Path(r"c:\Users\DELL\Downloads\consucorner-seo-redirects.csv")
RANK_MATH_COLUMNS = ["id", "source", "matching", "destination", "type", "category", "status", "ignore"]

NEW_DOMAIN = "https://consucorner.com"

DIAGNOSTIC_SUFFIXES = {
    "lenses-mirrors",
    "training-simulation",
    "occluders",
    "refraction-tools",
    "laryngoscopes",
    "anoscopes",
    "sigmoidoscopes",
    "speculums",
    "proctoscopes",
    "otoscopes",
}

SUFFIX_ALIASES = {
    "curette": "curettes",
    "retractor-speculum": "speculums-retractors",
    "retractors": "speculums-retractors",
    "retractor": "speculums-retractors",
    "chalazion-curettes": "curettes",
    "power-tools": "surgical-equipments",
    "hooks-manipulators": "hooks",
}

SPECIALTY_ALIASES = {"orthopedics": "orthopedic"}

DIRECT_MAP = {
    "surgical-instruments": "/surgical-instruments/",
    "ophthalmic-surgical-instruments": "/surgical-instruments/?specialty=ophthalmology",
    "diagnostic-examination-tools": "/diagnostic-instruments/",
    "medical-equipment": "/medical-equipment/",
    "endoscopy": "/endoscopy/",
    "consumables": "/consumables/",
    "general": "/consumables/?specialty=general-surgery",
    "general-surgery": "/specialty/general-surgery/",
    "orthopedic": "/specialty/orthopedic/",
    "orthopedics": "/specialty/orthopedic/",
    "neurosurgery": "/specialty/neurosurgery/",
    "refraction-tools": "/diagnostic-instruments/refraction-tools/",
    "needle-holders": "/surgical-instruments/needle-holders/",
    "scissors": "/surgical-instruments/scissors/",
    "forceps": "/surgical-instruments/forceps/",
    "miscellaneous": "/surgical-instruments/miscellaneous/",
    "clamps": "/surgical-instruments/clamps/",
    "ligature-needles": "/surgical-instruments/ligature-needles/",
    "retractor": "/surgical-instruments/speculums-retractors/",
    "rongeurs": "/surgical-instruments/rongeurs/",
    "punch": "/surgical-instruments/punch/",
    "curettes": "/surgical-instruments/curettes/",
    "dissectors": "/surgical-instruments/dissectors/",
    "hooks": "/surgical-instruments/hooks/",
    "surgical-equipments-general": "/medical-equipment/surgical-equipments/?specialty=general-surgery",
    "orthopedic-power-tools": "/medical-equipment/surgical-equipments/?specialty=orthopedic",
    "gynecology-endoscopy": "/endoscopy/?specialty=gynecology",
    "endoscopy-urology": "/endoscopy/?specialty=urology",
    "urology-endoscopy-instruments": "/endoscopy/endoscopy-instruments/?specialty=urology",
    "gynecology-endoscopy-instruments": "/endoscopy/endoscopy-instruments/?specialty=gynecology",
    "orthopedic-surgical-instruments": "/surgical-instruments/?specialty=orthopedic",
    "surgical-instruments-gynecology": "/surgical-instruments/?specialty=gynecology",
    "surgical-instruments-ent": "/surgical-instruments/?specialty=ent",
    "obstetrics-consumables": "/consumables/?specialty=gynecology",
}


def normalize_path(path: str) -> str:
    """Ensure relative path starts with / and ends with / before query string."""
    if not path.startswith("/"):
        path = "/" + path

    if "?" in path:
        base, query = path.split("?", 1)
        base = base.rstrip("/") + "/"
        return f"{base}?{query}"

    return path.rstrip("/") + "/"


def to_full_url(path: str) -> str:
    return NEW_DOMAIN + normalize_path(path)


def path_from_permalink(permalink: str) -> str:
    parsed = urlparse(permalink.rstrip("/") + "/")
    return parsed.path + (f"?{parsed.query}" if parsed.query else "")


def append_specialty(path: str, specialty: str) -> str:
    path = normalize_path(path.split("?")[0]) + (f"?{path.split('?', 1)[1]}" if "?" in path else "")
    if "?" in path:
        return path + f"&specialty={specialty}"
    return path.rstrip("/") + f"/?specialty={specialty}"


def map_suffix(suffix: str, specialty: str | None, new_paths: dict[str, str]) -> str:
    suffix = SUFFIX_ALIASES.get(suffix, suffix)

    if suffix in DIAGNOSTIC_SUFFIXES:
        base = f"/diagnostic-instruments/{suffix}/"
    elif suffix in new_paths:
        base = path_from_permalink(new_paths[suffix])
    elif suffix == "consumables":
        base = "/consumables/"
    elif suffix == "endoscopy":
        base = "/endoscopy/"
    elif suffix == "endoscopy-instruments":
        base = "/endoscopy/endoscopy-instruments/"
    else:
        base = f"/surgical-instruments/{suffix}/"

    if specialty:
        return append_specialty(base, specialty)
    return normalize_path(base)


def find_specialty_prefix(slug: str, prefixes: list[str]) -> tuple[str | None, str | None]:
    for prefix in prefixes:
        if slug == prefix:
            return SPECIALTY_ALIASES.get(prefix, prefix), ""
        if slug.startswith(prefix + "-"):
            return SPECIALTY_ALIASES.get(prefix, prefix), slug[len(prefix) + 1 :]
    return None, None


def map_old_slug(slug: str, specialties: set[str], new_paths: dict[str, str], prefixes: list[str]) -> tuple[str | None, str]:
    slug = slug.strip().lower()

    if slug in DIRECT_MAP:
        return DIRECT_MAP[slug], "direct"

    match = re.match(r"^diagnostic-instruments-(.+)$", slug)
    if match:
        specialty = SPECIALTY_ALIASES.get(match.group(1), match.group(1))
        if specialty in specialties:
            return f"/diagnostic-instruments/?specialty={specialty}", "diagnostic-specialty-hub"

    match = re.match(r"^surgical-instruments-(.+)$", slug)
    if match:
        specialty = SPECIALTY_ALIASES.get(match.group(1), match.group(1))
        if specialty in specialties:
            return f"/surgical-instruments/?specialty={specialty}", "surgical-specialty-hub"

    specialty, suffix = find_specialty_prefix(slug, prefixes)
    if specialty:
        if suffix == "":
            return f"/specialty/{specialty}/", "specialty-page"
        if suffix == "consumables":
            return f"/consumables/?specialty={specialty}", "specialty-consumables"
        return map_suffix(suffix, specialty, new_paths), "specialty-instrument"

    match = re.match(r"^(.+)-neurosurgery$", slug)
    if match:
        return map_suffix(match.group(1), "neurosurgery", new_paths), "neurosurgery-instrument"

    if slug in new_paths:
        return path_from_permalink(new_paths[slug]), "exact-new-category"

    return None, "unmapped"


def main() -> None:
    old_df = pd.read_excel(OLD_XLS)
    new_cat_df = pd.read_csv(NEW_CAT_CSV)
    new_spec_df = pd.read_csv(NEW_SPEC_CSV)

    specialties = set(new_spec_df["Term Slug"].str.strip())
    new_paths = {
        str(row["Term Slug"]).strip(): str(row["Term Permalink"]).strip()
        for _, row in new_cat_df.iterrows()
    }
    prefixes = sorted(specialties | set(SPECIALTY_ALIASES.keys()), key=len, reverse=True)

    rank_math_rows: list[dict[str, str]] = []
    human_rows: list[dict[str, str]] = []

    for _, record in old_df.iterrows():
        old_url = str(record["Term Permalink"]).rstrip("/") + "/"
        slug = str(record["Term Slug"]).strip()

        source_path = urlparse(old_url).path
        if not source_path.endswith("/"):
            source_path += "/"

        dest_path, method = map_old_slug(slug, specialties, new_paths, prefixes)
        new_url = to_full_url(dest_path) if dest_path else ""

        rank_math_rows.append(
            {
                "id": "",
                "source": source_path,
                "matching": "exact",
                "destination": new_url,
                "type": "301",
                "category": "old-site-migration",
                "status": "active",
                "ignore": "",
            }
        )
        human_rows.append(
            {
                "Source URL": source_path,
                "Source URL (Legacy Full)": old_url,
                "Destination URL": new_url,
                "Match Type": "Exact",
                "Redirection Type": "301",
                "Status": "Activate",
                "Ignore Case": "No",
                "Old Slug": slug,
                "Match Method": method,
                "Notes": "" if new_url else "NEEDS MANUAL REVIEW",
            }
        )

    out_df = pd.DataFrame(rank_math_rows, columns=RANK_MATH_COLUMNS)
    out_df.to_csv(OUTPUT_CSV, index=False, encoding="utf-8-sig")
    pd.DataFrame(human_rows).to_csv(HUMAN_CSV, index=False, encoding="utf-8-sig")

    mapped = out_df[out_df["destination"] != ""]
    unmapped = out_df[out_df["destination"] == ""]

    print(f"Wrote Rank Math import CSV to {OUTPUT_CSV}")
    print(f"Wrote human-readable CSV to {HUMAN_CSV}")
    print(f"Mapped: {len(mapped)} | Needs review: {len(unmapped)}")
    if len(unmapped):
        print(pd.DataFrame(human_rows).loc[unmapped.index, ["Old Slug", "Source URL"]].to_string(index=False))


if __name__ == "__main__":
    main()
