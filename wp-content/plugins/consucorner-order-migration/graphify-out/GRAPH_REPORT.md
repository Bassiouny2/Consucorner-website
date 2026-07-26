# Graph Report - consucorner-order-migration  (2026-07-01)

## Corpus Check
- 9 files · ~9,864 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 92 nodes · 145 edges · 14 communities (6 shown, 8 thin omitted)
- Extraction: 85% EXTRACTED · 15% INFERRED · 0% AMBIGUOUS · INFERRED: 22 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `190b675a`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 9|Community 9]]

## God Nodes (most connected - your core abstractions)
1. `CC_Order_Migrator` - 47 edges
2. `CC_Order_Migration_Config` - 19 edges
3. `CC_Order_Migration_Admin` - 14 edges
4. `WC_Order` - 11 edges
5. `CC_Order_Migration_Index` - 7 edges
6. `CC_Order_Migration_API` - 6 edges
7. `CC_Order_Migration_CLI` - 5 edges
8. `CC_Order_Migration_API` - 1 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Import Cycles
- None detected.

## Communities (14 total, 8 thin omitted)

## Knowledge Gaps
- **1 isolated node(s):** `CC_Order_Migration_API`
  These have ≤1 connection - possible missing edges or undocumented components.
- **8 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `CC_Order_Migrator` connect `Community 2` to `Community 0`, `Community 1`, `Community 3`, `Community 6`, `Community 7`, `Community 8`, `Community 9`, `Community 10`?**
  _High betweenness centrality (0.709) - this node is a cross-community bridge._
- **Why does `CC_Order_Migration_Config` connect `Community 1` to `Community 0`, `Community 7`?**
  _High betweenness centrality (0.215) - this node is a cross-community bridge._
- **Are the 6 inferred relationships involving `CC_Order_Migrator` (e.g. with `.handle_purge_trash()` and `.handle_sync_attribution()`) actually correct?**
  _`CC_Order_Migrator` has 6 INFERRED edges - model-reasoned connections that need verification._
- **Are the 11 inferred relationships involving `CC_Order_Migration_Config` (e.g. with `.handle_run()` and `.handle_save()`) actually correct?**
  _`CC_Order_Migration_Config` has 11 INFERRED edges - model-reasoned connections that need verification._
- **What connects `CC_Order_Migration_API` to the rest of the system?**
  _1 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.13333333333333333 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.14285714285714285 - nodes in this community are weakly interconnected._