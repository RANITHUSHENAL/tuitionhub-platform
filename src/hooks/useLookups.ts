import { useEffect, useState } from 'react';
import { lookupsApi, type Lookups } from '../lib/api';

/** One request per page load, shared by every component; a failed request is retried next time. */
let cached: Promise<Lookups> | null = null;

function load(): Promise<Lookups> {
  cached ??= lookupsApi.all().catch((error: unknown) => {
    cached = null;
    throw error;
  });

  return cached;
}

/**
 * The seeded taxonomy and districts from GET /api/v1/lookups (contract §4.6). While loading, or if
 * the API cannot be reached, the lists are empty and `failed` says so.
 */
export function useLookups() {
  const [lookups, setLookups] = useState<Lookups | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let alive = true;

    load()
      .then((result) => {
        if (alive) setLookups(result);
      })
      .catch(() => {
        if (alive) setFailed(true);
      });

    return () => {
      alive = false;
    };
  }, []);

  return {
    lookups,
    loading: lookups === null && !failed,
    failed,
    subjectNames: lookups?.subjects.map((s) => s.name) ?? [],
    gradeNames: lookups?.grades.map((g) => g.name) ?? [],
    examTargetNames: lookups?.exam_targets.map((e) => e.name) ?? [],
    mediumNames: lookups?.mediums.map((m) => m.name) ?? [],
    districts: lookups?.districts ?? []
  };
}
