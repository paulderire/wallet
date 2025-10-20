# 📊 Forex Dashboard - Visual Feature Map

## Dashboard Layout (Top to Bottom)

```
┌─────────────────────────────────────────────────────────────┐
│  📈 Forex Trading Journal                                    │
│  Track your trades, analyze performance, improve strategy   │
│  [+ Add Trade] [View All] [Analytics]                       │
└─────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬──────────────┬──────────────┐
│ 💰 Total P/L │ 🎯 Win Rate  │ 📊 Volume    │ 📈 Open      │
│  $12,450.00  │    65.5%     │   125.50     │      3       │
│  125 trades  │  82W / 43L   │  Lots traded │  Currently   │
└──────────────┴──────────────┴──────────────┴──────────────┘

┌──────────────┬──────────────┬──────────────┬──────────────┬──────────────┬──────────────┐
│ Profit Factor│  Avg Win     │  Avg Loss    │  Win Rate    │ Gross Profit │  Gross Loss  │
│    2.45      │   $150.61    │   $61.43     │    65.5%     │  $12,350.00  │   $5,040.00  │
│ EXCELLENT ✓  │   GOOD ✓     │  EXCELLENT ✓ │   GOOD ✓     │              │              │
└──────────────┴──────────────┴──────────────┴──────────────┴──────────────┴──────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 📈 Equity Curve                          [📊 Export Data]   │
│                                                              │
│  $15,000 ┐                                        ╱         │
│          │                              ╱────────╱          │
│  $10,000 ├────────────────────╱────────╱                   │
│          │            ╱──────╱                              │
│   $5,000 ├────╱─────╱                                       │
│          │   ╱                                              │
│       $0 └──────────────────────────────────────────────    │
│           Jan  Feb  Mar  Apr  May  Jun  Jul  Aug  Sep       │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────┬─────────────────────────────┐
│ 📅 Monthly Performance       │ 💱 Currency Pair Performance│
│                             │                             │
│ Sep 2025      $1,245.00 ↑  │ EUR/USD     $3,450.00 ↑    │
│ 15 trades · 66.7% win      │ 45 trades · 71% win        │
│                             │                             │
│ Aug 2025      $890.50 ↑    │ GBP/USD     $2,100.00 ↑    │
│ 12 trades · 58.3% win      │ 28 trades · 64% win        │
│                             │                             │
│ Jul 2025      -$320.00 ↓   │ USD/JPY     $1,890.00 ↑    │
│ 18 trades · 44.4% win      │ 32 trades · 59% win        │
│                             │                             │
│ Jun 2025      $2,340.00 ↑  │ AUD/USD     -$450.00 ↓     │
│ 22 trades · 77.3% win      │ 20 trades · 40% win        │
└─────────────────────────────┴─────────────────────────────┘

┌──────────────┬──────────────┬──────────────┬──────────────┐
│ Total Trades │  Avg Win     │  Avg Loss    │ Risk/Reward  │
│     125      │   $150.61    │   $61.43     │     2.45     │
└──────────────┴──────────────┴──────────────┴──────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 📋 Recent Trades                                             │
│                                                              │
│ Date       Pair    Type  Entry   Exit   Lots   P/L   Status│
│ ─────────────────────────────────────────────────────────── │
│ Oct 10  EUR/USD  BUY   1.0850  1.0920  0.50  +$350  CLOSED │
│ Oct 09  GBP/USD  SELL  1.2340  1.2300  0.30  +$120  CLOSED │
│ Oct 08  USD/JPY  BUY   149.50  150.20  0.25  +$175  CLOSED │
│ ...                                                         │
└─────────────────────────────────────────────────────────────┘
```

## Export Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│                 📊 Export Trading Data                       │
│         Download your forex trading history                 │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │          Ready to Export                            │    │
│  │                                                     │    │
│  │    125 Trades  │  110 Closed  │  +$12,450.00      │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────┐  ┌─────────────────────┐          │
│  │        📄          │  │        📋          │          │
│  │    CSV Format       │  │   JSON Format       │          │
│  │                     │  │                     │          │
│  │  Excel-compatible   │  │  Structured data    │          │
│  │  spreadsheet format │  │  format for import  │          │
│  └─────────────────────┘  └─────────────────────┘          │
│                                                              │
│              [← Back to Dashboard]                           │
└─────────────────────────────────────────────────────────────┘
```

## Color Coding Legend

### Performance Indicators
- 🟢 **Green** - Profits, Excellent performance (Profit Factor ≥2.0, Win Rate ≥60%)
- 🔵 **Blue** - Good performance (Profit Factor ≥1.5, Win Rate 50-60%)
- 🟠 **Orange** - Warning (Profit Factor 1.0-1.5, Win Rate 40-50%)
- 🔴 **Red** - Losses, Poor performance (Profit Factor <1.0, Win Rate <40%)

### Chart Colors
- **Equity Curve**: Green line for overall profit, Red for loss
- **Fill Area**: Semi-transparent green/red matching line color
- **Data Points**: White centers with colored borders

## Interactive Elements

### Hover Effects
- 📊 **Chart Points**: Show exact date and P/L value
- 📇 **Metric Cards**: Lift up 4-6px with shadow
- 🔘 **Export Buttons**: Border color change to purple
- 📋 **Table Rows**: Light background highlight

### Clickable Actions
1. **+ Add New Trade** → Create trade form
2. **View All Trades** → Complete trade list
3. **Analytics** → Detailed analytics page
4. **📊 Export Data** → Export selection page
5. **Currency Pairs** → Filter by pair (future)
6. **Monthly Items** → Monthly detail view (future)

## Data Flow

```
┌──────────────┐
│  MySQL DB    │
│ forex_trades │
└──────┬───────┘
       │
       ▼
┌──────────────────┐
│  PHP Backend     │
│  - Statistics    │
│  - Aggregations  │
│  - Calculations  │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│  JSON Data       │
│  equityData      │
│  monthlyData     │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│  Chart.js        │
│  Visualization   │
└──────────────────┘
```

## Metrics Calculation Examples

### Profit Factor
```
Total Wins:   $12,350.00 (82 winning trades)
Total Losses:  $5,040.00 (43 losing trades)
Profit Factor = 12,350 / 5,040 = 2.45 ✓ EXCELLENT
```

### Average Win/Loss
```
Avg Win  = $12,350 / 82 = $150.61
Avg Loss = $5,040 / 43 = $117.21
Risk/Reward = 150.61 / 117.21 = 1.28
```

### Win Rate
```
Closed Trades = 82 + 43 = 125
Win Rate = (82 / 125) × 100 = 65.6%
```

## Mobile Responsive Breakpoints

### Desktop (>768px)
- Stats grid: 4 columns
- Metrics: 6 columns
- Performance: 2 columns side-by-side

### Tablet (481-768px)
- Stats grid: 2 columns
- Metrics: 3 columns
- Performance: 1 column stacked

### Mobile (<480px)
- Stats grid: 1 column
- Metrics: 2 columns
- Performance: 1 column stacked
- Chart height: 250px (reduced)

## Browser Compatibility

✅ Chrome 90+
✅ Firefox 88+
✅ Safari 14+
✅ Edge 90+
✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Performance Optimizations

- **Lazy Loading**: Charts only render when data exists
- **SQL Optimization**: Single queries with aggregations
- **Caching**: JSON data passed once to JavaScript
- **CDN**: Chart.js loaded from fast CDN
- **Minimal DOM**: Efficient loops and conditional rendering
