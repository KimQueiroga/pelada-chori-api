# Grafana alert rules (base)

Use these expressions in Grafana Alerting. Start with environment="prod" and
duplicate for homol if needed. All expressions assume job="pelada_api".

## 1) 5xx error rate high
- Name: api_5xx_rate_high
- Query (A):
  - expr:
    sum(rate(http_requests_total{job="pelada_api",environment="prod",status=~"5.."}[5m]))
    /
    sum(rate(http_requests_total{job="pelada_api",environment="prod"}[5m]))
- Condition: A > 0.05
- For: 5m
- Evaluate every: 1m

## 2) p95 latency high
- Name: api_p95_latency_high_ms
- Query (A):
  - expr:
    histogram_quantile(
      0.95,
      sum(rate(http_request_duration_ms_bucket{job="pelada_api",environment="prod"}[5m])) by (le)
    )
- Condition: A > 800
- For: 5m
- Evaluate every: 1m

## 3) traffic missing (optional)
- Name: api_traffic_missing
- Query (A):
  - expr:
    sum(rate(http_requests_total{job="pelada_api",environment="prod"}[5m]))
- Condition: A < 0.1
- For: 10m
- Evaluate every: 1m
