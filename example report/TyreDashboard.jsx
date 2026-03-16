import { useState, useCallback, useMemo, useRef } from "react";
import {
  LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid,
  Tooltip, Legend, ResponsiveContainer, Cell, ReferenceLine
} from "recharts";

// ─── Colour palette ───────────────────────────────────────────────────────────
const C = {
  bg:       "#0A0F1E",
  surface:  "#111827",
  card:     "#151D2E",
  border:   "#1E2D45",
  teal:     "#00D4C8",
  tealDim:  "#00897B",
  gold:     "#FFB74D",
  rose:     "#F87171",
  blue:     "#60A5FA",
  purple:   "#A78BFA",
  green:    "#34D399",
  text:     "#E2E8F0",
  muted:    "#64748B",
  dim:      "#334155",
};

const YEAR_COLORS = ["#00D4C8", "#FFB74D", "#60A5FA", "#A78BFA", "#34D399"];
const MONTHS = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

// ─── Parser ───────────────────────────────────────────────────────────────────
function parseSellReport(text) {
  const lines = text.split(/\r?\n/).filter(l => l.trim());
  const headers = lines[0].split('\t').map(h => h.trim().replace(/^"|"$/g, ''));

  const idx = {};
  headers.forEach((h, i) => { idx[h] = i; });

  const DAY_NAMES = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

  const parseArea = (raw) => {
    if (!raw) return 'Unknown';
    const v = raw.trim().replace(/^"+|"+$/g, '').trim();
    const m = v.match(/-\s*([^:"]+)/);
    if (m) return m[1].trim().replace(/"+/g, '').trim();
    const m2 = v.match(/^([^:"]+)/);
    return m2 ? m2[1].trim() : 'Unknown';
  };

  const rows = [];
  for (let i = 1; i < lines.length; i++) {
    const parts = lines[i].split('\t');
    if (parts.length < 10) continue;

    const get = (key) => {
      const v = parts[idx[key]];
      return v ? v.trim().replace(/^"|"$/g, '') : '';
    };

    const qty   = parseFloat(get('Quantity'))       || 0;
    const price = parseFloat(get('Unit_Price'))      || 0;
    const cost  = parseFloat(get('Purchase_Price'))  || 0;
    const dateStr = get('Dated');

    // Parse DD/MM/YYYY HH:MM
    const dateParts = dateStr.match(/(\d+)\/(\d+)\/(\d+)\s+(\d+):(\d+)/);
    if (!dateParts) continue;
    const [, dd, mm, yyyy, hh] = dateParts;
    const month     = parseInt(mm);
    const year      = parseInt(yyyy);
    const hour      = parseInt(hh);
    const dateObj   = new Date(parseInt(yyyy), parseInt(mm)-1, parseInt(dd));
    const dayOfWeek = dateObj.getDay(); // 0=Sun..6=Sat
    const dayName   = DAY_NAMES[dayOfWeek];

    const revenue = qty * price;
    const profit  = (price - cost) * qty;
    const product = get('product');
    const brand   = product.split('-')[0].trim();
    const area    = parseArea(get('Delivery_Profile'));

    rows.push({
      year, month, hour, dayOfWeek, dayName,
      customer:  get('Business_Name').replace(/^"/, '').trim(),
      rep:       get('Sales_Rep'),
      invoice:   get('Invoice_Num'),
      product, brand, area,
      qty, price, cost,
      revenue, profit,
    });
  }
  return rows;
}

function aggregate(rows) {
  if (!rows.length) return null;
  const year = rows[0].year;

  // Monthly totals
  const monthMap = {};
  MONTHS.forEach((m, i) => { monthMap[i+1] = { month: m, revenue: 0, profit: 0, units: 0, invoices: new Set() }; });
  rows.forEach(r => {
    if (!monthMap[r.month]) return;
    monthMap[r.month].revenue  += r.revenue;
    monthMap[r.month].profit   += r.profit;
    monthMap[r.month].units    += r.qty;
    monthMap[r.month].invoices.add(r.invoice);
  });
  const monthly = Object.values(monthMap).map(m => ({
    ...m, invoices: m.invoices.size,
    margin: m.revenue ? m.profit / m.revenue * 100 : 0,
  }));

  // Months that actually have data (used for same-period retention comparison)
  const activeMonths = [...new Set(rows.map(r => r.month))].sort((a,b) => a-b);

  // Brands totals
  const brandMap = {};
  rows.forEach(r => {
    if (!brandMap[r.brand]) brandMap[r.brand] = { brand: r.brand, revenue: 0, profit: 0, units: 0 };
    brandMap[r.brand].revenue += r.revenue;
    brandMap[r.brand].profit  += r.profit;
    brandMap[r.brand].units   += r.qty;
  });
  const brands = Object.values(brandMap)
    .map(b => ({ ...b, margin: b.revenue ? b.profit / b.revenue * 100 : 0 }))
    .sort((a, b) => b.revenue - a.revenue);

  // Brand × month units  { brandName: { monthIndex: units } }
  const brandMonthly = {};
  rows.forEach(r => {
    if (!brandMonthly[r.brand]) brandMonthly[r.brand] = {};
    brandMonthly[r.brand][r.month] = (brandMonthly[r.brand][r.month] || 0) + r.qty;
  });

  // Reps totals + rep × month revenue  { repName: { monthIndex: revenue } }
  const repMap = {};
  const repMonthly = {};
  rows.forEach(r => {
    if (!repMap[r.rep]) repMap[r.rep] = { rep: r.rep, revenue: 0, profit: 0, units: 0, invoices: new Set() };
    repMap[r.rep].revenue  += r.revenue;
    repMap[r.rep].profit   += r.profit;
    repMap[r.rep].units    += r.qty;
    repMap[r.rep].invoices.add(r.invoice);
    if (!repMonthly[r.rep]) repMonthly[r.rep] = {};
    repMonthly[r.rep][r.month] = (repMonthly[r.rep][r.month] || 0) + r.revenue;
  });
  const reps = Object.values(repMap)
    .map(r => ({ ...r, invoices: r.invoices.size, margin: r.revenue ? r.profit / r.revenue * 100 : 0 }))
    .sort((a, b) => b.revenue - a.revenue);

  // Customers totals
  const custMap = {};
  rows.forEach(r => {
    const k = r.customer;
    if (!custMap[k]) custMap[k] = { customer: k, revenue: 0, profit: 0, units: 0, invoices: new Set() };
    custMap[k].revenue  += r.revenue;
    custMap[k].profit   += r.profit;
    custMap[k].units    += r.qty;
    custMap[k].invoices.add(r.invoice);
  });
  const customers = Object.values(custMap)
    .map(c => ({ ...c, invoices: c.invoices.size, margin: c.revenue ? c.profit / c.revenue * 100 : 0 }))
    .sort((a, b) => b.revenue - a.revenue);

  // Customer × month  { customerName: { monthIndex: { revenue, profit, units, invoices } } }
  // Used for same-period retention comparison without needing raw rows
  const customerMonthly = {};
  rows.forEach(r => {
    const k = r.customer;
    if (!customerMonthly[k]) customerMonthly[k] = {};
    if (!customerMonthly[k][r.month]) customerMonthly[k][r.month] = { revenue: 0, profit: 0, units: 0, invoices: new Set() };
    customerMonthly[k][r.month].revenue  += r.revenue;
    customerMonthly[k][r.month].profit   += r.profit;
    customerMonthly[k][r.month].units    += r.qty;
    customerMonthly[k][r.month].invoices.add(r.invoice);
  });
  Object.values(customerMonthly).forEach(cm =>
    Object.values(cm).forEach(m => { m.invoices = m.invoices.size; })
  );

  // Activity: day-of-week × hour heatmap (unique invoice counts)
  const invSeen   = new Set();
  const heatmap   = {};
  const dayInvMap = {};
  const dayRevMap = {};
  const dayUnitMap= {};
  const hourInvMap= {};
  const hourRevMap= {};
  const hourUnitMap={};
  rows.forEach(r => {
    const hmKey = `${r.dayOfWeek}_${r.hour}`;
    if (!invSeen.has(`${r.invoice}_${hmKey}`)) {
      invSeen.add(`${r.invoice}_${hmKey}`);
      heatmap[hmKey] = (heatmap[hmKey] || 0) + 1;
    }
    if (!dayInvMap[r.dayOfWeek]) dayInvMap[r.dayOfWeek] = new Set();
    dayInvMap[r.dayOfWeek].add(r.invoice);
    dayRevMap[r.dayOfWeek]  = (dayRevMap[r.dayOfWeek]  || 0) + r.revenue;
    dayUnitMap[r.dayOfWeek] = (dayUnitMap[r.dayOfWeek] || 0) + r.qty;
    if (!hourInvMap[r.hour]) hourInvMap[r.hour] = new Set();
    hourInvMap[r.hour].add(r.invoice);
    hourRevMap[r.hour]  = (hourRevMap[r.hour]  || 0) + r.revenue;
    hourUnitMap[r.hour] = (hourUnitMap[r.hour] || 0) + r.qty;
  });

  const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const byDayOfWeek = DAYS.map((d, i) => ({
    day: d, dayIndex: i,
    invoices: dayInvMap[i] ? dayInvMap[i].size : 0,
    revenue:  dayRevMap[i]  || 0,
    units:    dayUnitMap[i] || 0,
  }));

  const byHour = Array.from({ length: 24 }, (_, h) => ({
    hour: h,
    label: h === 0 ? '12am' : h < 12 ? `${h}am` : h === 12 ? '12pm' : `${h-12}pm`,
    invoices: hourInvMap[h] ? hourInvMap[h].size : 0,
    revenue:  hourRevMap[h]  || 0,
    units:    hourUnitMap[h] || 0,
  }));

  // Area analysis
  const areaMap = {};
  rows.forEach(r => {
    const k = r.area;
    if (!areaMap[k]) areaMap[k] = { area: k, revenue: 0, profit: 0, units: 0, invoices: new Set(), customers: new Set() };
    areaMap[k].revenue  += r.revenue;
    areaMap[k].profit   += r.profit;
    areaMap[k].units    += r.qty;
    areaMap[k].invoices.add(r.invoice);
    areaMap[k].customers.add(r.customer);
  });
  const byArea = Object.values(areaMap)
    .map(a => ({ ...a, invoices: a.invoices.size, customers: a.customers.size,
                  margin: a.revenue ? a.profit / a.revenue * 100 : 0 }))
    .sort((a, b) => b.revenue - a.revenue);

  const totalRevenue  = rows.reduce((s, r) => s + r.revenue, 0);
  const totalProfit   = rows.reduce((s, r) => s + r.profit, 0);
  const totalUnits    = rows.reduce((s, r) => s + r.qty, 0);
  const totalInvoices = new Set(rows.map(r => r.invoice)).size;
  const totalCusts    = new Set(rows.map(r => r.customer)).size;

  // NOTE: raw rows are NOT included in the returned object so it's safe to
  // serialise to localStorage (rows can be 5–10 MB per year).
  return { year, monthly, activeMonths, brands, brandMonthly, reps, repMonthly,
           customers, customerMonthly, heatmap, byDayOfWeek, byHour, byArea,
    totals: { revenue: totalRevenue, profit: totalProfit, units: totalUnits,
              invoices: totalInvoices, customers: totalCusts,
              margin: totalRevenue ? totalProfit / totalRevenue * 100 : 0 } };
}

// ─── Formatters ───────────────────────────────────────────────────────────────
const fmtAUD  = v => v == null ? '—' : `A$${Math.abs(v) >= 1e6 ? (v/1e6).toFixed(2)+'M' : Math.abs(v) >= 1e3 ? (v/1e3).toFixed(0)+'K' : v.toFixed(0)}`;
const fmtAUDf = v => v == null ? '—' : `A$${v.toLocaleString('en-AU', {minimumFractionDigits:0, maximumFractionDigits:0})}`;
const fmtPct  = v => v == null ? '—' : `${v.toFixed(1)}%`;
const fmtNum  = v => v == null ? '—' : v.toLocaleString('en-AU');

// ─── Sub-components ───────────────────────────────────────────────────────────
const styles = {
  mono: { fontFamily: "'Courier New', Courier, monospace" },
  sans: { fontFamily: "'DM Sans', 'Segoe UI', sans-serif" },
};

function KPICard({ label, value, sub, color = C.teal, trend }) {
  return (
    <div style={{
      background: C.card, border: `1px solid ${C.border}`, borderRadius: 12,
      padding: "20px 24px", flex: 1, minWidth: 160,
      borderTop: `3px solid ${color}`, position: 'relative', overflow: 'hidden',
    }}>
      <div style={{ fontSize: 11, color: C.muted, letterSpacing: '0.12em', textTransform: 'uppercase', marginBottom: 8, ...styles.sans }}>
        {label}
      </div>
      <div style={{ fontSize: 28, fontWeight: 700, color, ...styles.mono, lineHeight: 1 }}>
        {value}
      </div>
      {sub && <div style={{ fontSize: 12, color: C.muted, marginTop: 6, ...styles.sans }}>{sub}</div>}
      {trend != null && (
        <div style={{ fontSize: 12, marginTop: 6, color: trend >= 0 ? C.green : C.rose, ...styles.mono }}>
          {trend >= 0 ? '▲' : '▼'} {Math.abs(trend).toFixed(1)}% vs prev yr
        </div>
      )}
    </div>
  );
}

function SectionHeader({ title, sub }) {
  return (
    <div style={{ marginBottom: 16 }}>
      <div style={{ fontSize: 13, fontWeight: 700, color: C.text, letterSpacing: '0.06em', textTransform: 'uppercase', ...styles.sans }}>
        {title}
      </div>
      {sub && <div style={{ fontSize: 11, color: C.muted, marginTop: 2, ...styles.sans }}>{sub}</div>}
    </div>
  );
}

const CustomTooltip = ({ active, payload, label, prefix = "A$" }) => {
  if (!active || !payload?.length) return null;
  return (
    <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 8, padding: "10px 14px" }}>
      <div style={{ fontSize: 12, color: C.muted, marginBottom: 6, ...styles.sans }}>{label}</div>
      {payload.map((p, i) => (
        <div key={i} style={{ fontSize: 13, color: p.color, ...styles.mono }}>
          {p.name}: {prefix}{p.value?.toLocaleString('en-AU', {maximumFractionDigits: 0})}
        </div>
      ))}
    </div>
  );
};

const MarginTooltip = ({ active, payload, label }) => {
  if (!active || !payload?.length) return null;
  return (
    <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 8, padding: "10px 14px" }}>
      <div style={{ fontSize: 12, color: C.muted, marginBottom: 6, ...styles.sans }}>{label}</div>
      {payload.map((p, i) => (
        <div key={i} style={{ fontSize: 13, color: p.color, ...styles.mono }}>
          {p.name}: {p.value?.toFixed(1)}%
        </div>
      ))}
    </div>
  );
};

// ─── Upload Zone ──────────────────────────────────────────────────────────────
function UploadZone({ onFile }) {
  const [drag, setDrag] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const ref = useRef();

  const handle = useCallback((file) => {
    if (!file) return;
    setLoading(true); setError('');
    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const text = e.target.result;
        const rows = parseSellReport(text);
        if (!rows.length) { setError('No data rows found. Check file format.'); setLoading(false); return; }
        const agg = aggregate(rows);
        onFile(agg);
        setLoading(false);
      } catch (err) {
        setError('Parse error: ' + err.message);
        setLoading(false);
      }
    };
    reader.onerror = () => { setError('Could not read file.'); setLoading(false); };
    reader.readAsText(file);
  }, [onFile]);

  const onDrop = useCallback((e) => {
    e.preventDefault(); setDrag(false);
    handle(e.dataTransfer.files[0]);
  }, [handle]);

  return (
    <div
      onDrop={onDrop}
      onDragOver={e => { e.preventDefault(); setDrag(true); }}
      onDragLeave={() => setDrag(false)}
      onClick={() => ref.current.click()}
      style={{
        border: `2px dashed ${drag ? C.teal : C.border}`,
        borderRadius: 12, padding: "28px 32px",
        background: drag ? `${C.teal}10` : C.card,
        cursor: 'pointer', textAlign: 'center',
        transition: 'all 0.2s', minWidth: 280,
      }}>
      <input ref={ref} type="file" accept=".xls,.xlsx,.csv,.txt" style={{ display: 'none' }}
        onChange={e => handle(e.target.files[0])} />
      <div style={{ fontSize: 28, marginBottom: 8 }}>{loading ? '⏳' : '📂'}</div>
      <div style={{ fontSize: 13, color: C.text, ...styles.sans, fontWeight: 600 }}>
        {loading ? 'Processing...' : 'Drop Sell Report XLS'}
      </div>
      <div style={{ fontSize: 11, color: C.muted, marginTop: 4, ...styles.sans }}>
        Any year — auto-detected
      </div>
      {error && <div style={{ fontSize: 11, color: C.rose, marginTop: 8 }}>{error}</div>}
    </div>
  );
}

// ─── Main Dashboard ───────────────────────────────────────────────────────────
export default function TyreDashboard() {
  const [yearData, setYearData] = useState({});
  const [activeYears, setActiveYears] = useState([]);
  const [view, setView] = useState('overview');
  const [custTab, setCustTab] = useState('all');
  const [custYear, setCustYear] = useState(null);
  const [activityYear, setActivityYear] = useState(null);
  const [areaYear, setAreaYear] = useState(null);
  const [storageMsg, setStorageMsg] = useState('');

  // ── Load saved years from localStorage on first mount ─────────────────────
  const { useEffect } = React;
  useEffect(() => {
    try {
      const saved = JSON.parse(localStorage.getItem('tyreDashboard_index') || '[]');
      const loaded = {};
      saved.forEach(y => {
        try {
          const data = JSON.parse(localStorage.getItem(`tyreDashboard_${y}`));
          if (data) loaded[y] = data;
        } catch {}
      });
      if (Object.keys(loaded).length) {
        setYearData(loaded);
        setActiveYears(Object.keys(loaded).map(Number).sort());
      }
    } catch {}
  }, []);

  const saveToStorage = (agg) => {
    try {
      localStorage.setItem(`tyreDashboard_${agg.year}`, JSON.stringify(agg));
      const index = JSON.parse(localStorage.getItem('tyreDashboard_index') || '[]');
      if (!index.includes(agg.year)) {
        localStorage.setItem('tyreDashboard_index', JSON.stringify([...index, agg.year].sort()));
      }
      setStorageMsg(`✓ ${agg.year} saved`);
      setTimeout(() => setStorageMsg(''), 3000);
    } catch (e) {
      setStorageMsg('⚠ Could not save (storage full?)');
      setTimeout(() => setStorageMsg(''), 4000);
    }
  };

  const removeYear = (y) => {
    if (!window.confirm(`Remove ${y} data from saved storage?`)) return;
    localStorage.removeItem(`tyreDashboard_${y}`);
    const index = JSON.parse(localStorage.getItem('tyreDashboard_index') || '[]');
    localStorage.setItem('tyreDashboard_index', JSON.stringify(index.filter(i => i !== y)));
    setYearData(prev => { const n = { ...prev }; delete n[y]; return n; });
    setActiveYears(prev => prev.filter(ay => ay !== y));
  };

  const onFile = useCallback((agg) => {
    setYearData(prev => ({ ...prev, [agg.year]: agg }));
    setActiveYears(prev => prev.includes(agg.year) ? prev : [...prev, agg.year].sort());
    saveToStorage(agg);
  }, []);

  const years = Object.keys(yearData).map(Number).sort();
  const shownYears = activeYears.filter(y => yearData[y]);

  // Monthly comparison data
  const monthlyComparison = useMemo(() => {
    return MONTHS.map((m, i) => {
      const row = { month: m };
      shownYears.forEach(y => {
        const d = yearData[y]?.monthly[i];
        row[`rev_${y}`]    = d?.revenue || 0;
        row[`profit_${y}`] = d?.profit  || 0;
        row[`margin_${y}`] = d?.margin  || 0;
        row[`units_${y}`]  = d?.units   || 0;
      });
      return row;
    });
  }, [shownYears, yearData]);

  // Always-latest references for KPI cards / overview
  const latestYear = years[years.length - 1];
  const prevYear   = years[years.length - 2];
  const latestData = yearData[latestYear];
  const prevData   = yearData[prevYear];

  const yoyTrend = (key) => {
    if (!latestData || !prevData) return null;
    const cur = latestData.totals[key], pre = prevData.totals[key];
    return pre ? (cur - pre) / pre * 100 : null;
  };

  const brandData = useMemo(() => latestData ? latestData.brands.slice(0, 15) : [], [latestData]);

  // Top N brands across ALL loaded years combined (top 8 by total units)
  const TOP_BRANDS = useMemo(() => {
    if (!years.length) return [];
    const totals = {};
    years.forEach(y => {
      (yearData[y]?.brands || []).forEach(b => {
        totals[b.brand] = (totals[b.brand] || 0) + b.units;
      });
    });
    return Object.entries(totals).sort((a, b) => b[1] - a[1]).slice(0, 8).map(([b]) => b);
  }, [years, yearData]);

  const BRAND_COLORS = ["#00D4C8","#FFB74D","#60A5FA","#A78BFA","#34D399","#F87171","#FB923C","#E879F9"];

  // For the stacked chart: one dataset per year (driven by brandChartYear state)
  const [brandChartYear, setBrandChartYear] = useState(null);
  const brandChartYearEff = (brandChartYear && yearData[brandChartYear]) ? brandChartYear : latestYear;

  const brandMonthlyUnits = useMemo(() => {
    const yd = yearData[brandChartYearEff];
    if (!yd || !TOP_BRANDS.length) return [];
    return MONTHS.map((m, mi) => {
      const row = { month: m };
      TOP_BRANDS.forEach(brand => {
        row[brand] = Math.round(yd.brandMonthly?.[brand]?.[mi + 1] || 0);
      });
      return row;
    });
  }, [brandChartYearEff, yearData, TOP_BRANDS]);

  // Pivot: rows = brand+year combos, cols = months — all years shown together
  // Structure: [ { brand, year, Jan, Feb, ..., Total } ]
  const brandMonthlyPivot = useMemo(() => {
    if (!TOP_BRANDS.length) return [];
    const rows = [];
    TOP_BRANDS.forEach(brand => {
      years.forEach(y => {
        const yd = yearData[y];
        if (!yd) return;
        const row = { brand, year: y };
        let total = 0;
        MONTHS.forEach((m, mi) => {
          const units = Math.round(yd.brandMonthly?.[brand]?.[mi + 1] || 0);
          row[m] = units;
          total += units;
        });
        row['Total'] = total;
        rows.push(row);
      });
    });
    return rows;
  }, [years, yearData, TOP_BRANDS]);

  // Column totals per month+year for the pivot footer
  const pivotColTotals = useMemo(() => {
    const result = {};
    years.forEach(y => {
      MONTHS.forEach(m => {
        const key = `${m}_${y}`;
        result[key] = brandMonthlyPivot
          .filter(r => r.year === y)
          .reduce((s, r) => s + (r[m] || 0), 0);
      });
      result[`Total_${y}`] = brandMonthlyPivot
        .filter(r => r.year === y)
        .reduce((s, r) => s + (r['Total'] || 0), 0);
    });
    return result;
  }, [brandMonthlyPivot, years]);

  // ── Customer tab: selected year (cur) vs the year before it (cmp) ──────────
  const custCurYear = custYear && yearData[custYear] ? custYear : latestYear;
  const custCmpYear = years[years.indexOf(custCurYear) - 1]; // year before selected
  const custCurData = yearData[custCurYear];
  const custCmpData = yearData[custCmpYear];

  const custAnalytics = useMemo(() => {
    if (!custCurData || !custCmpData) return null;

    // Month range from pre-aggregated activeMonths (replaces scanning raw rows)
    const curMonths = new Set(custCurData.activeMonths || []);
    const minMonth  = Math.min(...curMonths);
    const maxMonth  = Math.max(...curMonths);

    // Same-period slice of comparison year using customerMonthly (replaces row filtering)
    const cmpPeriodMap = {};
    Object.entries(custCmpData.customerMonthly || {}).forEach(([customer, monthData]) => {
      Object.entries(monthData).forEach(([mStr, data]) => {
        const m = parseInt(mStr);
        if (m >= minMonth && m <= maxMonth) {
          if (!cmpPeriodMap[customer]) cmpPeriodMap[customer] = { revenue: 0, profit: 0, units: 0, invoices: 0 };
          cmpPeriodMap[customer].revenue  += data.revenue;
          cmpPeriodMap[customer].profit   += data.profit;
          cmpPeriodMap[customer].units    += data.units;
          cmpPeriodMap[customer].invoices += data.invoices;
        }
      });
    });

    const curMap  = {};
    custCurData.customers.forEach(c => { curMap[c.customer] = c; });

    const curNames = new Set(Object.keys(curMap));
    const cmpNames = new Set(Object.keys(cmpPeriodMap));

    const retained = [], gained = [], lost = [];

    curNames.forEach(name => {
      if (cmpNames.has(name)) {
        const cur = curMap[name], cmp = cmpPeriodMap[name];
        const revChg = cmp.revenue ? (cur.revenue - cmp.revenue) / cmp.revenue * 100 : null;
        retained.push({ ...cur, prevRevenue: cmp.revenue, prevUnits: cmp.units, revChg });
      } else {
        gained.push({ ...curMap[name] });
      }
    });

    cmpNames.forEach(name => {
      if (!curNames.has(name)) {
        lost.push({ ...cmpPeriodMap[name], customer: name, samePeriodRevenue: cmpPeriodMap[name].revenue,
          margin: cmpPeriodMap[name].revenue ? cmpPeriodMap[name].profit / cmpPeriodMap[name].revenue * 100 : 0 });
      }
    });

    retained.sort((a, b) => b.revenue - a.revenue);
    gained.sort((a, b) => b.revenue - a.revenue);
    lost.sort((a, b) => b.samePeriodRevenue - a.samePeriodRevenue);

    const retainedRevCur  = retained.reduce((s, c) => s + c.revenue, 0);
    const retainedRevCmp  = retained.reduce((s, c) => s + c.prevRevenue, 0);
    const gainedRev       = gained.reduce((s, c) => s + c.revenue, 0);
    const lostRev         = lost.reduce((s, c) => s + c.samePeriodRevenue, 0);
    const retentionRate   = cmpNames.size ? (retained.length / cmpNames.size * 100) : null;

    const mNames = Array.from(curMonths).sort((a, b) => a - b).map(m => MONTHS[m - 1]);
    const periodLabel = mNames.length === 1 ? mNames[0] : `${mNames[0]}–${mNames[mNames.length - 1]}`;

    return { retained, gained, lost,
             retainedRevLatest: retainedRevCur, retainedRevPrev: retainedRevCmp,
             gainedRev, lostRev, retentionRate, periodLabel,
             totalPrev: cmpNames.size, totalLatest: curNames.size };
  }, [custCurData, custCmpData]);

  const hasData = years.length > 0;

  const navBtn = (v, label) => (
    <button onClick={() => setView(v)} style={{
      background: view === v ? C.teal : 'transparent',
      color: view === v ? C.bg : C.muted,
      border: `1px solid ${view === v ? C.teal : C.dim}`,
      borderRadius: 8, padding: "6px 16px", cursor: 'pointer',
      fontSize: 12, fontWeight: 600, ...styles.sans,
      transition: 'all 0.15s',
    }}>{label}</button>
  );

  return (
    <div style={{ background: C.bg, minHeight: '100vh', color: C.text, padding: 24, ...styles.sans }}>

      {/* ── Header ── */}
      <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 28, flexWrap: 'wrap', gap: 16 }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 4 }}>
            <div style={{ width: 36, height: 36, borderRadius: 10, background: `linear-gradient(135deg, ${C.teal}, ${C.tealDim})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 18 }}>🏎️</div>
            <div>
              <div style={{ fontSize: 20, fontWeight: 800, letterSpacing: '-0.02em', color: C.text }}>Tyre Retail Intelligence</div>
              <div style={{ fontSize: 11, color: C.muted, letterSpacing: '0.08em' }}>SALES ANALYTICS DASHBOARD</div>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
          {/* Year pills with remove button */}
          {years.map((y, i) => (
            <div key={y} style={{ display: 'flex', alignItems: 'center', gap: 0 }}>
              <button onClick={() => setActiveYears(prev =>
                prev.includes(y) ? prev.filter(x => x !== y) : [...prev, y].sort()
              )} style={{
                background: activeYears.includes(y) ? `${YEAR_COLORS[i % YEAR_COLORS.length]}20` : 'transparent',
                border: `1px solid ${activeYears.includes(y) ? YEAR_COLORS[i % YEAR_COLORS.length] : C.dim}`,
                borderRight: 'none',
                color: activeYears.includes(y) ? YEAR_COLORS[i % YEAR_COLORS.length] : C.muted,
                borderRadius: '20px 0 0 20px', padding: "5px 14px", cursor: 'pointer',
                fontSize: 13, fontWeight: 700, ...styles.mono, transition: 'all 0.15s',
              }}>{y}</button>
              <button onClick={() => removeYear(y)} title={`Remove ${y} from storage`} style={{
                background: 'transparent',
                border: `1px solid ${activeYears.includes(y) ? YEAR_COLORS[i % YEAR_COLORS.length] : C.dim}`,
                borderLeft: `1px solid ${C.dim}`,
                color: C.dim, borderRadius: '0 20px 20px 0', padding: "5px 8px", cursor: 'pointer',
                fontSize: 11, lineHeight: 1, transition: 'all 0.15s',
              }} onMouseEnter={e => e.target.style.color = C.rose}
                 onMouseLeave={e => e.target.style.color = C.dim}>✕</button>
            </div>
          ))}
          {storageMsg && (
            <span style={{ fontSize: 11, color: storageMsg.startsWith('✓') ? C.green : C.gold, ...styles.mono }}>
              {storageMsg}
            </span>
          )}
          <UploadZone onFile={onFile} />
        </div>
      </div>

      {!hasData && (
        <div style={{ textAlign: 'center', marginTop: 120 }}>
          <div style={{ fontSize: 64, marginBottom: 16 }}>📊</div>
          <div style={{ fontSize: 20, fontWeight: 700, color: C.text, marginBottom: 8 }}>Upload your first Sell Report</div>
          <div style={{ fontSize: 14, color: C.muted }}>Drop any year's SellReport XLS — once uploaded, data saves automatically and reloads next time you open the app</div>
        </div>
      )}

      {hasData && (
        <>
          {/* ── Nav ── */}
          <div style={{ display: 'flex', gap: 8, marginBottom: 24, flexWrap: 'wrap' }}>
            {navBtn('overview',  '📊 Overview')}
            {navBtn('monthly',   '📅 Monthly Trends')}
            {navBtn('brands',    '🔖 Brands')}
            {navBtn('customers', '🏆 Customers')}
            {navBtn('reps',      '👤 Sales Reps')}
            {navBtn('activity',  '⏱ Activity & Areas')}
          </div>

          {/* ── KPI Cards (shown on all views) ── */}
          {latestData && (
            <div style={{ display: 'flex', gap: 12, marginBottom: 24, flexWrap: 'wrap' }}>
              <KPICard label={`Revenue ex-GST (${latestYear})`} color={C.teal}
                value={fmtAUD(latestData.totals.revenue)}
                sub={`inc-GST: ${fmtAUD(latestData.totals.revenue * 1.1)}`}
                trend={yoyTrend('revenue')} />
              <KPICard label={`Gross Profit (${latestYear})`} color={C.gold}
                value={fmtAUD(latestData.totals.profit)}
                sub={`Margin: ${fmtPct(latestData.totals.margin)}`}
                trend={yoyTrend('profit')} />
              <KPICard label="Total Units" color={C.blue}
                value={fmtNum(Math.round(latestData.totals.units))}
                sub="Tyres sold"
                trend={yoyTrend('units')} />
              <KPICard label="Active Customers" color={C.purple}
                value={latestData.totals.customers}
                sub={`${fmtNum(latestData.totals.invoices)} invoices`} />
              <KPICard label="Avg Invoice Value" color={C.green}
                value={fmtAUD(latestData.totals.revenue / latestData.totals.invoices)}
                sub="ex-GST" />
            </div>
          )}

          {/* ── OVERVIEW ── */}
          {view === 'overview' && (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>

              {/* Monthly Revenue */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20, gridColumn: '1 / -1' }}>
                <SectionHeader title="Monthly Revenue (ex-GST)" sub="All loaded years" />
                <ResponsiveContainer width="100%" height={260}>
                  <BarChart data={monthlyComparison} barGap={4} barCategoryGap="25%">
                    <CartesianGrid strokeDasharray="3 3" stroke={C.border} vertical={false} />
                    <XAxis dataKey="month" tick={{ fill: C.muted, fontSize: 11 }} axisLine={false} tickLine={false} />
                    <YAxis tickFormatter={v => fmtAUD(v)} tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <Tooltip content={<CustomTooltip />} />
                    <Legend wrapperStyle={{ fontSize: 12, color: C.muted }} />
                    {shownYears.map((y, i) => (
                      <Bar key={y} dataKey={`rev_${y}`} name={String(y)}
                        fill={YEAR_COLORS[i % YEAR_COLORS.length]} radius={[3, 3, 0, 0]} />
                    ))}
                  </BarChart>
                </ResponsiveContainer>
              </div>

              {/* GP Margin trend */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <SectionHeader title="GP Margin % by Month" />
                <ResponsiveContainer width="100%" height={220}>
                  <LineChart data={monthlyComparison}>
                    <CartesianGrid strokeDasharray="3 3" stroke={C.border} vertical={false} />
                    <XAxis dataKey="month" tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <YAxis tickFormatter={v => v.toFixed(0) + '%'} tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <Tooltip content={<MarginTooltip />} />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    <ReferenceLine y={12} stroke={C.dim} strokeDasharray="4 4" label={{ value: 'Target', fill: C.muted, fontSize: 10 }} />
                    {shownYears.map((y, i) => (
                      <Line key={y} dataKey={`margin_${y}`} name={String(y)}
                        stroke={YEAR_COLORS[i % YEAR_COLORS.length]} strokeWidth={2.5}
                        dot={{ r: 3, fill: YEAR_COLORS[i % YEAR_COLORS.length] }} />
                    ))}
                  </LineChart>
                </ResponsiveContainer>
              </div>

              {/* Top 8 brands */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <SectionHeader title={`Brand Revenue (${latestYear})`} sub="Top 8" />
                <ResponsiveContainer width="100%" height={220}>
                  <BarChart data={brandData.slice(0, 8)} layout="vertical" barSize={18}>
                    <CartesianGrid strokeDasharray="3 3" stroke={C.border} horizontal={false} />
                    <XAxis type="number" tickFormatter={v => fmtAUD(v)} tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <YAxis type="category" dataKey="brand" tick={{ fill: C.muted, fontSize: 11 }} axisLine={false} tickLine={false} width={70} />
                    <Tooltip content={<CustomTooltip />} />
                    <Bar dataKey="revenue" name="Revenue" radius={[0, 4, 4, 0]}>
                      {brandData.slice(0, 8).map((b, i) => (
                        <Cell key={i} fill={b.margin < 0 ? C.rose : b.margin < 8 ? C.gold : C.teal} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>

            </div>
          )}

          {/* ── MONTHLY TRENDS ── */}
          {view === 'monthly' && (
            <div style={{ display: 'grid', gap: 20 }}>

              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <SectionHeader title="Revenue by Month — Year on Year" sub="ex-GST" />
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={monthlyComparison}>
                    <CartesianGrid strokeDasharray="3 3" stroke={C.border} vertical={false} />
                    <XAxis dataKey="month" tick={{ fill: C.muted, fontSize: 11 }} axisLine={false} tickLine={false} />
                    <YAxis tickFormatter={v => fmtAUD(v)} tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <Tooltip content={<CustomTooltip />} />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    {shownYears.map((y, i) => (
                      <Line key={y} dataKey={`rev_${y}`} name={String(y)}
                        stroke={YEAR_COLORS[i % YEAR_COLORS.length]} strokeWidth={2.5}
                        dot={{ r: 4, fill: YEAR_COLORS[i % YEAR_COLORS.length] }} />
                    ))}
                  </LineChart>
                </ResponsiveContainer>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                  <SectionHeader title="Gross Profit by Month" />
                  <ResponsiveContainer width="100%" height={240}>
                    <BarChart data={monthlyComparison} barGap={4}>
                      <CartesianGrid strokeDasharray="3 3" stroke={C.border} vertical={false} />
                      <XAxis dataKey="month" tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                      <YAxis tickFormatter={v => fmtAUD(v)} tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                      <Tooltip content={<CustomTooltip />} />
                      <Legend wrapperStyle={{ fontSize: 12 }} />
                      {shownYears.map((y, i) => (
                        <Bar key={y} dataKey={`profit_${y}`} name={String(y)}
                          fill={YEAR_COLORS[i % YEAR_COLORS.length]} radius={[3, 3, 0, 0]} />
                      ))}
                    </BarChart>
                  </ResponsiveContainer>
                </div>

                <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                  <SectionHeader title="Units Sold by Month" />
                  <ResponsiveContainer width="100%" height={240}>
                    <BarChart data={monthlyComparison} barGap={4}>
                      <CartesianGrid strokeDasharray="3 3" stroke={C.border} vertical={false} />
                      <XAxis dataKey="month" tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                      <YAxis tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                      <Tooltip content={<CustomTooltip prefix="" />} />
                      <Legend wrapperStyle={{ fontSize: 12 }} />
                      {shownYears.map((y, i) => (
                        <Bar key={y} dataKey={`units_${y}`} name={String(y)}
                          fill={YEAR_COLORS[i % YEAR_COLORS.length]} radius={[3, 3, 0, 0]} />
                      ))}
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </div>

              {/* Monthly detail table */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <SectionHeader title={`Monthly Detail — ${latestYear}`} />
                {latestData && (
                  <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                    <thead>
                      <tr>
                        {['Month','Revenue ex-GST','Revenue inc-GST','Units','Invoices','Gross Profit','GP Margin %','vs Prev Mth'].map(h => (
                          <th key={h} style={{ textAlign: 'right', padding: "8px 12px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, letterSpacing: '0.04em', whiteSpace: 'nowrap' }}>
                            {h}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {latestData.monthly.map((m, i) => {
                        const prev = i > 0 ? latestData.monthly[i-1] : null;
                        const chg  = prev && prev.revenue ? (m.revenue - prev.revenue) / prev.revenue * 100 : null;
                        return (
                          <tr key={m.month} style={{ background: i % 2 === 0 ? C.surface : 'transparent' }}>
                            <td style={{ padding: "9px 12px", color: C.text, fontWeight: 600, textAlign: 'right', ...styles.mono }}>{m.month}</td>
                            <td style={{ padding: "9px 12px", color: C.teal, textAlign: 'right', ...styles.mono }}>{fmtAUDf(m.revenue)}</td>
                            <td style={{ padding: "9px 12px", color: C.muted, textAlign: 'right', ...styles.mono }}>{fmtAUDf(m.revenue * 1.1)}</td>
                            <td style={{ padding: "9px 12px", color: C.text, textAlign: 'right', ...styles.mono }}>{fmtNum(Math.round(m.units))}</td>
                            <td style={{ padding: "9px 12px", color: C.text, textAlign: 'right', ...styles.mono }}>{fmtNum(m.invoices)}</td>
                            <td style={{ padding: "9px 12px", color: C.gold, textAlign: 'right', ...styles.mono }}>{fmtAUDf(m.profit)}</td>
                            <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, color: m.margin < 8 ? C.rose : m.margin >= 12 ? C.green : C.text }}>{fmtPct(m.margin)}</td>
                            <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, color: chg == null ? C.muted : chg >= 0 ? C.green : C.rose }}>
                              {chg == null ? '—' : `${chg >= 0 ? '▲' : '▼'} ${Math.abs(chg).toFixed(1)}%`}
                            </td>
                          </tr>
                        );
                      })}
                      <tr style={{ borderTop: `1px solid ${C.teal}` }}>
                        <td style={{ padding: "10px 12px", color: C.teal, fontWeight: 800, textAlign: 'right', ...styles.mono }}>TOTAL</td>
                        <td style={{ padding: "10px 12px", color: C.teal, fontWeight: 700, textAlign: 'right', ...styles.mono }}>{fmtAUDf(latestData.totals.revenue)}</td>
                        <td style={{ padding: "10px 12px", color: C.muted, fontWeight: 700, textAlign: 'right', ...styles.mono }}>{fmtAUDf(latestData.totals.revenue * 1.1)}</td>
                        <td style={{ padding: "10px 12px", fontWeight: 700, textAlign: 'right', ...styles.mono }}>{fmtNum(Math.round(latestData.totals.units))}</td>
                        <td style={{ padding: "10px 12px", fontWeight: 700, textAlign: 'right', ...styles.mono }}>{fmtNum(latestData.totals.invoices)}</td>
                        <td style={{ padding: "10px 12px", color: C.gold, fontWeight: 700, textAlign: 'right', ...styles.mono }}>{fmtAUDf(latestData.totals.profit)}</td>
                        <td style={{ padding: "10px 12px", fontWeight: 700, textAlign: 'right', ...styles.mono }}>{fmtPct(latestData.totals.margin)}</td>
                        <td style={{ padding: "10px 12px", textAlign: 'right' }}>—</td>
                      </tr>
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          )}

          {/* ── BRANDS ── */}
          {view === 'brands' && latestData && (
            <div style={{ display: 'grid', gap: 20 }}>

              {/* Revenue bar chart */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <SectionHeader title={`Brand Revenue (${latestYear})`} sub="Colour = margin tier: red < 0%, amber 0–8%, teal 8%+" />
                <ResponsiveContainer width="100%" height={320}>
                  <BarChart data={brandData} layout="vertical" barSize={20}>
                    <CartesianGrid strokeDasharray="3 3" stroke={C.border} horizontal={false} />
                    <XAxis type="number" tickFormatter={v => fmtAUD(v)} tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <YAxis type="category" dataKey="brand" tick={{ fill: C.text, fontSize: 11 }} axisLine={false} tickLine={false} width={80} />
                    <Tooltip content={<CustomTooltip />} />
                    <Bar dataKey="revenue" name="Revenue" radius={[0, 4, 4, 0]}>
                      {brandData.map((b, i) => (
                        <Cell key={i} fill={b.margin < 0 ? C.rose : b.margin < 8 ? C.gold : C.teal} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>

              {/* Monthly units stacked bar chart */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 700, color: C.text, letterSpacing: '0.06em', textTransform: 'uppercase' }}>Units Sold per Month by Brand</div>
                    <div style={{ fontSize: 11, color: C.muted, marginTop: 2 }}>Top {TOP_BRANDS.length} brands by volume — stacked</div>
                  </div>
                  <div style={{ display: 'flex', gap: 6 }}>
                    {years.map((y, i) => (
                      <button key={y} onClick={() => setBrandChartYear(y)} style={{
                        background: brandChartYearEff === y ? `${YEAR_COLORS[i % YEAR_COLORS.length]}25` : 'transparent',
                        color: brandChartYearEff === y ? YEAR_COLORS[i % YEAR_COLORS.length] : C.muted,
                        border: `1px solid ${brandChartYearEff === y ? YEAR_COLORS[i % YEAR_COLORS.length] : C.dim}`,
                        borderRadius: 16, padding: "4px 14px", cursor: 'pointer',
                        fontSize: 12, fontWeight: 700, fontFamily: 'monospace',
                      }}>{y}</button>
                    ))}
                  </div>
                </div>
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={brandMonthlyUnits} barCategoryGap="20%">
                    <CartesianGrid strokeDasharray="3 3" stroke={C.border} vertical={false} />
                    <XAxis dataKey="month" tick={{ fill: C.muted, fontSize: 11 }} axisLine={false} tickLine={false} />
                    <YAxis tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <Tooltip
                      content={({ active, payload, label }) => {
                        if (!active || !payload?.length) return null;
                        const total = payload.reduce((s, p) => s + (p.value || 0), 0);
                        return (
                          <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 8, padding: "10px 14px", minWidth: 160 }}>
                            <div style={{ fontSize: 12, color: C.muted, marginBottom: 6, ...styles.sans }}>{label} {brandChartYearEff}</div>
                            {[...payload].reverse().map((p, i) => p.value > 0 && (
                              <div key={i} style={{ fontSize: 12, color: p.fill, ...styles.mono, display: 'flex', justifyContent: 'space-between', gap: 16 }}>
                                <span>{p.dataKey}</span><span>{fmtNum(p.value)}</span>
                              </div>
                            ))}
                            <div style={{ fontSize: 12, color: C.text, fontWeight: 700, borderTop: `1px solid ${C.border}`, marginTop: 6, paddingTop: 6, ...styles.mono, display: 'flex', justifyContent: 'space-between' }}>
                              <span>Total</span><span>{fmtNum(total)}</span>
                            </div>
                          </div>
                        );
                      }}
                    />
                    <Legend wrapperStyle={{ fontSize: 11 }} />
                    {TOP_BRANDS.map((brand, i) => (
                      <Bar key={brand} dataKey={brand} stackId="a" fill={BRAND_COLORS[i % BRAND_COLORS.length]}
                        radius={i === TOP_BRANDS.length - 1 ? [3, 3, 0, 0] : [0, 0, 0, 0]} />
                    ))}
                  </BarChart>
                </ResponsiveContainer>
              </div>

              {/* Monthly units pivot table — multi-year */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <SectionHeader title="Units Sold — Monthly Breakdown by Brand & Year" sub={`Top ${TOP_BRANDS.length} brands · ${years.join(' vs ')}`} />
                <div style={{ overflowX: 'auto' }}>
                  <table style={{ borderCollapse: 'collapse', fontSize: 11, minWidth: '100%' }}>
                    <thead>
                      <tr>
                        <th style={{ textAlign: 'left', padding: "8px 12px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, letterSpacing: '0.04em', minWidth: 80, position: 'sticky', left: 0, background: C.card }}>Brand</th>
                        <th style={{ textAlign: 'center', padding: "8px 8px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, minWidth: 44 }}>Yr</th>
                        {MONTHS.map(m => (
                          <th key={m} style={{ textAlign: 'right', padding: "8px 8px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, minWidth: 46 }}>{m}</th>
                        ))}
                        <th style={{ textAlign: 'right', padding: "8px 12px", color: C.teal, borderBottom: `1px solid ${C.border}`, fontWeight: 700, fontSize: 11, minWidth: 58 }}>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {TOP_BRANDS.map((brand, bi) => {
                        const brandRows = brandMonthlyPivot.filter(r => r.brand === brand);
                        return brandRows.map((row, yi) => {
                          const isFirst = yi === 0;
                          const isLast  = yi === brandRows.length - 1;
                          // YoY change for Total column (latest vs prev year for this brand)
                          const prevRow = yi > 0 ? brandRows[yi - 1] : null;
                          const yoyChg  = prevRow && prevRow.Total
                            ? (row.Total - prevRow.Total) / prevRow.Total * 100 : null;
                          return (
                            <tr key={`${brand}-${row.year}`} style={{
                              background: bi % 2 === 0 ? C.surface : 'transparent',
                              borderBottom: isLast ? `1px solid ${C.border}` : 'none',
                            }}>
                              {/* Brand cell only on first year row */}
                              <td style={{
                                padding: isFirst ? "10px 12px 4px" : "4px 12px 10px",
                                fontWeight: 700, color: BRAND_COLORS[bi % BRAND_COLORS.length],
                                position: 'sticky', left: 0,
                                background: bi % 2 === 0 ? C.surface : C.card,
                                fontSize: 12, verticalAlign: 'middle',
                              }}>
                                {isFirst && (
                                  <>
                                    <span style={{ display: 'inline-block', width: 8, height: 8, borderRadius: '50%', background: BRAND_COLORS[bi % BRAND_COLORS.length], marginRight: 6 }} />
                                    {brand}
                                  </>
                                )}
                              </td>
                              {/* Year badge */}
                              <td style={{ padding: "6px 8px", textAlign: 'center', ...styles.mono, fontSize: 11,
                                color: YEAR_COLORS[years.indexOf(row.year) % YEAR_COLORS.length], fontWeight: 700 }}>
                                {row.year}
                              </td>
                              {/* Month cells */}
                              {MONTHS.map(m => (
                                <td key={m} style={{ padding: "6px 8px", textAlign: 'right', ...styles.mono, color: row[m] > 0 ? C.text : C.dim }}>
                                  {row[m] > 0 ? fmtNum(row[m]) : '—'}
                                </td>
                              ))}
                              {/* Total + YoY */}
                              <td style={{ padding: "6px 12px", textAlign: 'right', ...styles.mono, fontWeight: 700 }}>
                                <span style={{ color: C.teal }}>{fmtNum(row.Total)}</span>
                                {yoyChg != null && (
                                  <span style={{ display: 'block', fontSize: 10, color: yoyChg >= 0 ? C.green : C.rose, fontWeight: 600 }}>
                                    {yoyChg >= 0 ? '▲' : '▼'} {Math.abs(yoyChg).toFixed(0)}%
                                  </span>
                                )}
                              </td>
                            </tr>
                          );
                        });
                      })}
                      {/* Totals rows — one per year */}
                      {years.map((y, yi) => (
                        <tr key={`total-${y}`} style={{ borderTop: yi === 0 ? `2px solid ${C.teal}` : 'none', background: C.card }}>
                          <td style={{ padding: "9px 12px", fontWeight: 800, color: C.teal, position: 'sticky', left: 0, background: C.card, fontSize: 12 }}>
                            {yi === 0 ? 'TOTAL' : ''}
                          </td>
                          <td style={{ padding: "6px 8px", textAlign: 'center', ...styles.mono, fontSize: 11,
                            color: YEAR_COLORS[yi % YEAR_COLORS.length], fontWeight: 700 }}>{y}</td>
                          {MONTHS.map(m => (
                            <td key={m} style={{ padding: "9px 8px", textAlign: 'right', ...styles.mono, fontWeight: 700, color: C.teal }}>
                              {pivotColTotals[`${m}_${y}`] > 0 ? fmtNum(pivotColTotals[`${m}_${y}`]) : '—'}
                            </td>
                          ))}
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, fontWeight: 800, color: C.teal }}>
                            {fmtNum(pivotColTotals[`Total_${y}`])}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Brand summary table */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <SectionHeader title="Full Brand Summary" sub="All brands — sorted by revenue" />
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                  <thead>
                    <tr>
                      {['#','Brand','Revenue ex-GST','Rev %','Total Units','Gross Profit','GP Margin'].map(h => (
                        <th key={h} style={{ textAlign: h === '#' || h === 'Brand' ? 'left' : 'right', padding: "8px 12px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, letterSpacing: '0.04em' }}>{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {brandData.map((b, i) => (
                      <tr key={b.brand} style={{ background: i % 2 === 0 ? C.surface : 'transparent' }}>
                        <td style={{ padding: "9px 12px", color: C.muted, ...styles.mono }}>{i + 1}</td>
                        <td style={{ padding: "9px 12px", color: C.text, fontWeight: 600 }}>{b.brand}</td>
                        <td style={{ padding: "9px 12px", color: C.teal, textAlign: 'right', ...styles.mono }}>{fmtAUDf(b.revenue)}</td>
                        <td style={{ padding: "9px 12px", color: C.muted, textAlign: 'right', ...styles.mono }}>{fmtPct(b.revenue / latestData.totals.revenue * 100)}</td>
                        <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(Math.round(b.units))}</td>
                        <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, color: b.profit < 0 ? C.rose : C.gold }}>{fmtAUDf(b.profit)}</td>
                        <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, fontWeight: 700,
                          color: b.margin < 0 ? C.rose : b.margin < 8 ? C.gold : C.green }}>{fmtPct(b.margin)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* ── CUSTOMERS ── */}
          {view === 'customers' && custCurData && (
            <div style={{ display: 'grid', gap: 20 }}>

              {/* Year selector */}
              <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                <span style={{ fontSize: 12, color: C.muted, ...styles.sans }}>Viewing year:</span>
                {years.map((y, i) => (
                  <button key={y} onClick={() => { setCustYear(y); setCustTab('all'); }}
                    style={{
                      background: custCurYear === y ? `${YEAR_COLORS[i % YEAR_COLORS.length]}25` : 'transparent',
                      color: custCurYear === y ? YEAR_COLORS[i % YEAR_COLORS.length] : C.muted,
                      border: `1px solid ${custCurYear === y ? YEAR_COLORS[i % YEAR_COLORS.length] : C.dim}`,
                      borderRadius: 20, padding: "5px 18px", cursor: 'pointer',
                      fontSize: 13, fontWeight: 700, ...styles.mono, transition: 'all 0.15s',
                    }}>{y}</button>
                ))}
                {custCmpYear
                  ? <span style={{ fontSize: 11, color: C.muted, ...styles.sans }}>← comparing vs {custCmpYear} (same period)</span>
                  : <span style={{ fontSize: 11, color: C.muted, ...styles.sans }}>← upload {custCurYear - 1} to unlock retention analysis</span>
                }
              </div>

              {/* Retention KPI cards — only if comparison year available */}
              {custAnalytics && (
                <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: "18px 24px", flex: 1, minWidth: 160, borderTop: `3px solid ${C.teal}` }}>
                    <div style={{ fontSize: 11, color: C.muted, letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 6, ...styles.sans }}>Retention Rate</div>
                    <div style={{ fontSize: 30, fontWeight: 800, color: custAnalytics.retentionRate >= 80 ? C.green : custAnalytics.retentionRate >= 60 ? C.gold : C.rose, ...styles.mono }}>
                      {fmtPct(custAnalytics.retentionRate)}
                    </div>
                    <div style={{ fontSize: 11, color: C.muted, marginTop: 4 }}>{custAnalytics.retained.length} of {custAnalytics.totalPrev} kept ({custAnalytics.periodLabel})</div>
                  </div>
                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: "18px 24px", flex: 1, minWidth: 160, borderTop: `3px solid ${C.green}` }}>
                    <div style={{ fontSize: 11, color: C.muted, letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 6, ...styles.sans }}>New Customers</div>
                    <div style={{ fontSize: 30, fontWeight: 800, color: C.green, ...styles.mono }}>{custAnalytics.gained.length}</div>
                    <div style={{ fontSize: 11, color: C.muted, marginTop: 4 }}>+{fmtAUD(custAnalytics.gainedRev)} revenue</div>
                  </div>
                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: "18px 24px", flex: 1, minWidth: 160, borderTop: `3px solid ${C.rose}` }}>
                    <div style={{ fontSize: 11, color: C.muted, letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 6, ...styles.sans }}>Lost Customers</div>
                    <div style={{ fontSize: 30, fontWeight: 800, color: C.rose, ...styles.mono }}>{custAnalytics.lost.length}</div>
                    <div style={{ fontSize: 11, color: C.muted, marginTop: 4 }}>{fmtAUD(custAnalytics.lostRev)} same-period rev at risk</div>
                  </div>
                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: "18px 24px", flex: 1, minWidth: 160, borderTop: `3px solid ${C.gold}` }}>
                    <div style={{ fontSize: 11, color: C.muted, letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 6, ...styles.sans }}>Retained Revenue Growth</div>
                    <div style={{ fontSize: 30, fontWeight: 800, color: custAnalytics.retainedRevLatest >= custAnalytics.retainedRevPrev ? C.green : C.rose, ...styles.mono }}>
                      {custAnalytics.retainedRevPrev ? `${custAnalytics.retainedRevLatest >= custAnalytics.retainedRevPrev ? '+' : ''}${((custAnalytics.retainedRevLatest - custAnalytics.retainedRevPrev) / custAnalytics.retainedRevPrev * 100).toFixed(1)}%` : '—'}
                    </div>
                    <div style={{ fontSize: 11, color: C.muted, marginTop: 4 }}>{fmtAUD(custAnalytics.retainedRevLatest)} vs {fmtAUD(custAnalytics.retainedRevPrev)}</div>
                  </div>
                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: "18px 24px", flex: 1, minWidth: 160, borderTop: `3px solid ${C.purple}` }}>
                    <div style={{ fontSize: 11, color: C.muted, letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 6, ...styles.sans }}>Net Customer Change</div>
                    <div style={{ fontSize: 30, fontWeight: 800, color: custAnalytics.totalLatest >= custAnalytics.totalPrev ? C.green : C.rose, ...styles.mono }}>
                      {custAnalytics.totalLatest >= custAnalytics.totalPrev ? '+' : ''}{custAnalytics.totalLatest - custAnalytics.totalPrev}
                    </div>
                    <div style={{ fontSize: 11, color: C.muted, marginTop: 4 }}>{custAnalytics.totalPrev} → {custAnalytics.totalLatest} accounts</div>
                  </div>
                </div>
              )}

              {/* Sub-tabs */}
              <div style={{ display: 'flex', gap: 8 }}>
                {[
                  ['all',      `All (${custCurData.customers.length})`,                          C.teal],
                  ['retained', custAnalytics ? `Retained (${custAnalytics.retained.length})` : 'Retained', C.gold],
                  ['gained',   custAnalytics ? `New (${custAnalytics.gained.length})`         : 'New',      C.green],
                  ['lost',     custAnalytics ? `Lost (${custAnalytics.lost.length})`          : 'Lost',     C.rose],
                ].map(([tab, label, color]) => (
                  <button key={tab} onClick={() => setCustTab(tab)}
                    disabled={!custAnalytics && tab !== 'all'}
                    style={{
                      background: custTab === tab ? `${color}20` : 'transparent',
                      color: custTab === tab ? color : C.muted,
                      border: `1px solid ${custTab === tab ? color : C.dim}`,
                      borderRadius: 8, padding: "6px 16px", cursor: custAnalytics || tab === 'all' ? 'pointer' : 'not-allowed',
                      fontSize: 12, fontWeight: 600, ...styles.sans, opacity: !custAnalytics && tab !== 'all' ? 0.4 : 1,
                    }}>{label}</button>
                ))}
                {!custAnalytics && <span style={{ fontSize: 11, color: C.muted, alignSelf: 'center', marginLeft: 8 }}>↑ Upload {prevYear ? prevYear - 1 : 'another'} year to unlock retention analysis</span>}
              </div>

              {/* ALL customers table */}
              {custTab === 'all' && (
                <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                  <SectionHeader title={`All Customers — ${custCurYear}`} sub="Sorted by revenue" />
                  <div style={{ overflowX: 'auto' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                    <thead>
                      <tr>
                        {['#','Customer','Status','Revenue ex-GST','Rev %','Units','Invoices','Gross Profit','GP Margin'].map(h => (
                          <th key={h} style={{ textAlign: ['#','Customer','Status'].includes(h) ? 'left' : 'right', padding: "8px 12px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, letterSpacing: '0.04em', whiteSpace: 'nowrap' }}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {custCurData.customers.map((c, i) => {
                        const isNew = custAnalytics && !custAnalytics.retained.find(r => r.customer === c.customer);
                        const statusLabel = !custAnalytics ? null : isNew ? '🟢 New' : '🔵 Retained';
                        return (
                          <tr key={c.customer} style={{ background: i % 2 === 0 ? C.surface : 'transparent' }}>
                            <td style={{ padding: "9px 12px", color: C.muted, ...styles.mono }}>{i + 1}</td>
                            <td style={{ padding: "9px 12px", color: C.text, fontWeight: 500, maxWidth: 260, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.customer}</td>
                            <td style={{ padding: "9px 12px", fontSize: 11 }}>{statusLabel || '—'}</td>
                            <td style={{ padding: "9px 12px", color: C.teal, textAlign: 'right', ...styles.mono }}>{fmtAUDf(c.revenue)}</td>
                            <td style={{ padding: "9px 12px", color: C.muted, textAlign: 'right', ...styles.mono }}>{fmtPct(c.revenue / custCurData.totals.revenue * 100)}</td>
                            <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(Math.round(c.units))}</td>
                            <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(c.invoices)}</td>
                            <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, color: c.profit < 0 ? C.rose : C.gold }}>{fmtAUDf(c.profit)}</td>
                            <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, fontWeight: 700,
                              color: c.margin < 0 ? C.rose : c.margin < 8 ? C.gold : C.green }}>{fmtPct(c.margin)}</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                  </div>
                </div>
              )}

              {/* RETAINED customers */}
              {custTab === 'retained' && custAnalytics && (
                <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                  <SectionHeader title={`Retained Customers — ${custCmpYear} → ${custCurYear}`} sub={`Comparing same period (${custAnalytics.periodLabel}) in both years — sorted by current revenue`} />
                  <div style={{ overflowX: 'auto' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                    <thead>
                      <tr>
                        {['#','Customer',`Rev ${custCurYear}`,`Rev ${custCmpYear} (same period)`,'A$ Change','Units','Invoices','GP Margin','% Trend'].map(h => (
                          <th key={h} style={{ textAlign: ['#','Customer'].includes(h) ? 'left' : 'right', padding: "8px 12px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, letterSpacing: '0.04em', whiteSpace: 'nowrap' }}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {custAnalytics.retained.map((c, i) => (
                        <tr key={c.customer} style={{ background: i % 2 === 0 ? C.surface : 'transparent' }}>
                          <td style={{ padding: "9px 12px", color: C.muted, ...styles.mono }}>{i + 1}</td>
                          <td style={{ padding: "9px 12px", color: C.text, fontWeight: 500, maxWidth: 260, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.customer}</td>
                          <td style={{ padding: "9px 12px", color: C.teal, textAlign: 'right', ...styles.mono }}>{fmtAUDf(c.revenue)}</td>
                          <td style={{ padding: "9px 12px", color: C.muted, textAlign: 'right', ...styles.mono }}>{fmtAUDf(c.prevRevenue)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, color: c.revChg >= 0 ? C.green : C.rose }}>
                            {c.revChg != null ? `${c.revChg >= 0 ? '+' : ''}${fmtAUDf(c.revenue - c.prevRevenue)}` : '—'}
                          </td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(Math.round(c.units))}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(c.invoices)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, fontWeight: 700,
                            color: c.margin < 0 ? C.rose : c.margin < 8 ? C.gold : C.green }}>{fmtPct(c.margin)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, fontWeight: 700,
                            color: c.revChg >= 0 ? C.green : C.rose }}>
                            {c.revChg != null ? `${c.revChg >= 0 ? '▲' : '▼'} ${Math.abs(c.revChg).toFixed(1)}%` : '—'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  </div>
                </div>
              )}

              {/* NEW / GAINED customers */}
              {custTab === 'gained' && custAnalytics && (
                <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                  <SectionHeader title={`New Customers in ${custCurYear}`} sub={`${custAnalytics.gained.length} accounts not seen in ${custCmpYear} (${custAnalytics.periodLabel}) — total new revenue: ${fmtAUDf(custAnalytics.gainedRev)}`} />
                  <div style={{ overflowX: 'auto' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                    <thead>
                      <tr>
                        {['#','Customer','Revenue ex-GST','Rev %','Units','Invoices','Gross Profit','GP Margin'].map(h => (
                          <th key={h} style={{ textAlign: ['#','Customer'].includes(h) ? 'left' : 'right', padding: "8px 12px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, letterSpacing: '0.04em', whiteSpace: 'nowrap' }}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {custAnalytics.gained.map((c, i) => (
                        <tr key={c.customer} style={{ background: i % 2 === 0 ? C.surface : 'transparent' }}>
                          <td style={{ padding: "9px 12px", color: C.muted, ...styles.mono }}>{i + 1}</td>
                          <td style={{ padding: "9px 12px", color: C.text, fontWeight: 500, maxWidth: 280, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            <span style={{ color: C.green, marginRight: 6, fontSize: 10 }}>🟢 NEW</span>{c.customer}
                          </td>
                          <td style={{ padding: "9px 12px", color: C.teal, textAlign: 'right', ...styles.mono }}>{fmtAUDf(c.revenue)}</td>
                          <td style={{ padding: "9px 12px", color: C.muted, textAlign: 'right', ...styles.mono }}>{fmtPct(c.revenue / custCurData.totals.revenue * 100)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(Math.round(c.units))}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(c.invoices)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, color: c.profit < 0 ? C.rose : C.gold }}>{fmtAUDf(c.profit)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, fontWeight: 700,
                            color: c.margin < 0 ? C.rose : c.margin < 8 ? C.gold : C.green }}>{fmtPct(c.margin)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  </div>
                </div>
              )}

              {/* LOST customers */}
              {custTab === 'lost' && custAnalytics && (
                <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                  <SectionHeader title={`Lost Customers — not seen in ${custCurYear}`} sub={`Bought in ${custCmpYear} (${custAnalytics.periodLabel}) but not in ${custCurYear} — ${fmtAUDf(custAnalytics.lostRev)} same-period revenue at risk`} />
                  <div style={{ overflowX: 'auto' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                    <thead>
                      <tr>
                        {['#','Customer',`${custCmpYear} Same-Period Rev`,`Rev % of ${custCmpYear} period`,'Units','Invoices','GP Margin'].map(h => (
                          <th key={h} style={{ textAlign: ['#','Customer'].includes(h) ? 'left' : 'right', padding: "8px 12px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, letterSpacing: '0.04em', whiteSpace: 'nowrap' }}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {custAnalytics.lost.map((c, i) => (
                        <tr key={c.customer} style={{ background: i % 2 === 0 ? C.surface : 'transparent' }}>
                          <td style={{ padding: "9px 12px", color: C.muted, ...styles.mono }}>{i + 1}</td>
                          <td style={{ padding: "9px 12px", color: C.text, fontWeight: 500, maxWidth: 280, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            <span style={{ color: C.rose, marginRight: 6, fontSize: 10 }}>🔴 LOST</span>{c.customer}
                          </td>
                          <td style={{ padding: "9px 12px", color: C.rose, textAlign: 'right', ...styles.mono }}>{fmtAUDf(c.samePeriodRevenue)}</td>
                          <td style={{ padding: "9px 12px", color: C.muted, textAlign: 'right', ...styles.mono }}>{fmtPct(c.samePeriodRevenue / (custCmpData?.totals?.revenue || 1) * 100)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(Math.round(c.units))}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(c.invoices)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, fontWeight: 700,
                            color: c.margin < 0 ? C.rose : c.margin < 8 ? C.gold : C.green }}>{fmtPct(c.margin)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  </div>
                </div>
              )}

            </div>
          )}

          {/* ── SALES REPS ── */}
          {view === 'reps' && latestData && (
            <div style={{ display: 'grid', gap: 20 }}>
              {/* Rep summary cards */}
              <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
                {latestData.reps.map((rep, i) => (
                  <div key={rep.rep} style={{
                    background: C.card, border: `1px solid ${C.border}`, borderRadius: 12,
                    padding: 24, flex: 1, minWidth: 220,
                    borderLeft: `4px solid ${YEAR_COLORS[i % YEAR_COLORS.length]}`,
                  }}>
                    <div style={{ fontSize: 18, fontWeight: 800, color: C.text, marginBottom: 16 }}>
                      👤 {rep.rep}
                    </div>
                    {[
                      ['Revenue ex-GST', fmtAUDf(rep.revenue), C.teal],
                      ['Revenue inc-GST', fmtAUDf(rep.revenue * 1.1), C.muted],
                      ['Gross Profit', fmtAUDf(rep.profit), C.gold],
                      ['GP Margin', fmtPct(rep.margin), rep.margin >= 12 ? C.green : rep.margin >= 8 ? C.gold : C.rose],
                      ['Units Sold', fmtNum(Math.round(rep.units)), C.blue],
                      ['Invoices', fmtNum(rep.invoices), C.text],
                      ['Rev Share', fmtPct(rep.revenue / latestData.totals.revenue * 100), C.purple],
                    ].map(([label, val, color]) => (
                      <div key={label} style={{ display: 'flex', justifyContent: 'space-between', padding: "6px 0", borderBottom: `1px solid ${C.border}` }}>
                        <span style={{ fontSize: 11, color: C.muted }}>{label}</span>
                        <span style={{ fontSize: 13, color, ...styles.mono, fontWeight: 600 }}>{val}</span>
                      </div>
                    ))}
                  </div>
                ))}
              </div>

              {/* Monthly rep comparison */}
              <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                <SectionHeader title={`Monthly Revenue by Rep — ${latestYear}`} />
                <ResponsiveContainer width="100%" height={280}>
                  <BarChart
                    data={latestData.monthly.map((m, mi) => {
                      const row = { month: m.month };
                      latestData.reps.forEach(rep => {
                        row[rep.rep] = latestData.repMonthly?.[rep.rep]?.[mi + 1] || 0;
                      });
                      return row;
                    })}
                    barGap={4}
                  >
                    <CartesianGrid strokeDasharray="3 3" stroke={C.border} vertical={false} />
                    <XAxis dataKey="month" tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <YAxis tickFormatter={v => fmtAUD(v)} tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                    <Tooltip content={<CustomTooltip />} />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    {latestData.reps.map((rep, i) => (
                      <Bar key={rep.rep} dataKey={rep.rep} fill={YEAR_COLORS[i % YEAR_COLORS.length]} radius={[3, 3, 0, 0]} />
                    ))}
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </div>
          )}

          {/* ── ACTIVITY & AREAS ── */}
          {view === 'activity' && latestData && (() => {
            const DAYS_ORDER = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
            const DAYS_IDX   = { Mon:1, Tue:2, Wed:3, Thu:4, Fri:5, Sat:6, Sun:0 };

            // Resolved years (fall back to latest if state is null / not loaded)
            const actYr  = (activityYear && yearData[activityYear]) ? activityYear : latestYear;
            const areaYr = (areaYear     && yearData[areaYear])     ? areaYear     : latestYear;
            const actD   = yearData[actYr];
            const areaD  = yearData[areaYr];

            // Year pill renderer (shared helper)
            const YearPills = ({ selected, onSelect }) => (
              <div style={{ display: 'flex', gap: 5 }}>
                {years.map((y, i) => (
                  <button key={y} onClick={() => onSelect(y)} style={{
                    background: selected === y ? `${YEAR_COLORS[i % YEAR_COLORS.length]}25` : 'transparent',
                    color: selected === y ? YEAR_COLORS[i % YEAR_COLORS.length] : C.muted,
                    border: `1px solid ${selected === y ? YEAR_COLORS[i % YEAR_COLORS.length] : C.dim}`,
                    borderRadius: 16, padding: "3px 14px", cursor: 'pointer',
                    fontSize: 12, fontWeight: 700, fontFamily: 'monospace', transition: 'all 0.15s',
                  }}>{y}</button>
                ))}
              </div>
            );

            // Activity data (day/hour)
            const dowOrdered = DAYS_ORDER.map(d => actD.byDayOfWeek.find(r => r.day === d));
            const maxDowInv  = Math.max(...dowOrdered.map(r => r?.invoices || 0));
            const BIZ_HOURS  = Array.from({ length: 15 }, (_, i) => i + 6);
            const maxHeatVal = Math.max(1, ...Object.values(actD.heatmap));

            const heatColor = (v) => {
              if (!v) return C.surface;
              const pct = v / maxHeatVal;
              if (pct < 0.25) return '#1E3A5F';
              if (pct < 0.50) return '#1A6B8A';
              if (pct < 0.75) return '#00897B';
              return '#00D4C8';
            };

            // Area data
            const totalAreaRev = areaD.byArea.reduce((s, a) => s + a.revenue, 0);

            return (
              <div style={{ display: 'grid', gap: 20 }}>

                {/* ── ACTIVITY SECTION ── */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: "14px 20px" }}>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 700, color: C.text, textTransform: 'uppercase', letterSpacing: '0.06em' }}>⏱ Order Activity Analysis</div>
                    <div style={{ fontSize: 11, color: C.muted, marginTop: 2 }}>Busiest days & times — unique invoice counts</div>
                  </div>
                  <YearPills selected={actYr} onSelect={setActivityYear} />
                </div>

                {/* Day-of-week + Hourly bars */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                    <SectionHeader title={`Orders by Day of Week — ${actYr}`} sub="Unique invoices Mon–Sun" />
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 8 }}>
                      {dowOrdered.map((r) => {
                        if (!r) return null;
                        const pct = maxDowInv ? r.invoices / maxDowInv : 0;
                        const isWeekend = r.day === 'Sat' || r.day === 'Sun';
                        return (
                          <div key={r.day} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                            <div style={{ width: 32, fontSize: 12, fontWeight: 700, color: isWeekend ? C.muted : C.text, ...styles.mono, flexShrink: 0 }}>{r.day}</div>
                            <div style={{ flex: 1, height: 28, background: C.surface, borderRadius: 4, overflow: 'hidden', position: 'relative' }}>
                              <div style={{
                                width: `${pct * 100}%`, height: '100%',
                                background: isWeekend ? C.dim : `linear-gradient(90deg, ${C.teal}, #00897B)`,
                                borderRadius: 4, transition: 'width 0.4s ease',
                              }} />
                              <span style={{ position: 'absolute', right: 8, top: '50%', transform: 'translateY(-50%)', fontSize: 11, color: C.muted, ...styles.mono }}>
                                {fmtNum(r.invoices)}
                              </span>
                            </div>
                            <div style={{ width: 60, fontSize: 11, color: C.muted, textAlign: 'right', ...styles.mono, flexShrink: 0 }}>
                              {fmtAUD(r.revenue)}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                    <SectionHeader title={`Orders by Hour of Day — ${actYr}`} sub="Unique invoices, business hours" />
                    <ResponsiveContainer width="100%" height={220}>
                      <BarChart data={actD.byHour.filter(h => h.hour >= 6 && h.hour <= 19)} barSize={18}>
                        <CartesianGrid strokeDasharray="3 3" stroke={C.border} vertical={false} />
                        <XAxis dataKey="label" tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                        <Tooltip content={({ active, payload, label }) => {
                          if (!active || !payload?.length) return null;
                          return (
                            <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 8, padding: "8px 12px" }}>
                              <div style={{ fontSize: 12, color: C.muted, ...styles.sans }}>{label}</div>
                              <div style={{ fontSize: 13, color: C.teal, ...styles.mono }}>{fmtNum(payload[0].value)} invoices</div>
                              <div style={{ fontSize: 12, color: C.gold, ...styles.mono }}>{fmtAUD(payload[0].payload.revenue)}</div>
                            </div>
                          );
                        }} />
                        <Bar dataKey="invoices" name="Invoices" radius={[3, 3, 0, 0]}>
                          {actD.byHour.filter(h => h.hour >= 6 && h.hour <= 19).map((h, i) => {
                            const maxInv = Math.max(...actD.byHour.map(x => x.invoices));
                            return <Cell key={i} fill={h.invoices === maxInv ? C.gold : C.teal} />;
                          })}
                        </Bar>
                      </BarChart>
                    </ResponsiveContainer>
                  </div>
                </div>

                {/* Heatmap */}
                <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                  <SectionHeader title={`Order Heatmap — Day × Hour (${actYr})`} sub="Darker = more invoices. Business hours 6am–8pm shown." />
                  <div style={{ overflowX: 'auto' }}>
                    <table style={{ borderCollapse: 'separate', borderSpacing: 3, fontSize: 11 }}>
                      <thead>
                        <tr>
                          <th style={{ width: 36, color: C.muted, fontSize: 10, padding: '4px 6px', textAlign: 'left' }}></th>
                          {BIZ_HOURS.map(h => (
                            <th key={h} style={{ color: C.muted, fontSize: 10, padding: '4px 6px', textAlign: 'center', minWidth: 38, fontWeight: 600 }}>
                              {h < 12 ? `${h}am` : h === 12 ? '12pm' : `${h-12}pm`}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {DAYS_ORDER.map(day => {
                          const dowIdx    = DAYS_IDX[day];
                          const isWeekend = day === 'Sat' || day === 'Sun';
                          return (
                            <tr key={day}>
                              <td style={{ color: isWeekend ? C.dim : C.muted, fontSize: 11, fontWeight: 700, padding: '4px 8px 4px 0', ...styles.mono }}>{day}</td>
                              {BIZ_HOURS.map(h => {
                                const val = actD.heatmap[`${dowIdx}_${h}`] || 0;
                                return (
                                  <td key={h} title={`${day} ${h < 12 ? h+'am' : h === 12 ? '12pm' : (h-12)+'pm'}: ${val} orders`}
                                    style={{
                                      width: 38, height: 32, borderRadius: 4,
                                      background: heatColor(val),
                                      textAlign: 'center', verticalAlign: 'middle',
                                      color: val > maxHeatVal * 0.5 ? C.bg : C.muted,
                                      fontSize: 10, fontWeight: 600, cursor: 'default', ...styles.mono,
                                    }}>
                                    {val > 0 ? val : ''}
                                  </td>
                                );
                              })}
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 12 }}>
                      <span style={{ fontSize: 10, color: C.muted }}>Low</span>
                      {['#1E3A5F','#1A6B8A','#00897B','#00D4C8'].map(c => (
                        <div key={c} style={{ width: 24, height: 16, borderRadius: 3, background: c }} />
                      ))}
                      <span style={{ fontSize: 10, color: C.muted }}>High</span>
                    </div>
                  </div>
                </div>

                {/* ── AREA SECTION ── */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: "14px 20px", marginTop: 8 }}>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 700, color: C.text, textTransform: 'uppercase', letterSpacing: '0.06em' }}>📍 Delivery Area Analysis</div>
                    <div style={{ fontSize: 11, color: C.muted, marginTop: 2 }}>From Delivery_Profile field — area extracted after dash</div>
                  </div>
                  <YearPills selected={areaYr} onSelect={setAreaYear} />
                </div>

                {/* Area charts */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                    <SectionHeader title={`Revenue by Delivery Area — ${areaYr}`} />
                    <ResponsiveContainer width="100%" height={240}>
                      <BarChart data={areaD.byArea.slice(0, 8)} layout="vertical" barSize={20}>
                        <CartesianGrid strokeDasharray="3 3" stroke={C.border} horizontal={false} />
                        <XAxis type="number" tickFormatter={v => fmtAUD(v)} tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                        <YAxis type="category" dataKey="area" tick={{ fill: C.text, fontSize: 11 }} axisLine={false} tickLine={false} width={100} />
                        <Tooltip content={<CustomTooltip />} />
                        <Bar dataKey="revenue" name="Revenue" radius={[0, 4, 4, 0]}>
                          {areaD.byArea.slice(0, 8).map((_, i) => (
                            <Cell key={i} fill={YEAR_COLORS[i % YEAR_COLORS.length]} />
                          ))}
                        </Bar>
                      </BarChart>
                    </ResponsiveContainer>
                  </div>

                  <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                    <SectionHeader title={`Units by Delivery Area — ${areaYr}`} />
                    <ResponsiveContainer width="100%" height={240}>
                      <BarChart data={areaD.byArea.slice(0, 8)} layout="vertical" barSize={20}>
                        <CartesianGrid strokeDasharray="3 3" stroke={C.border} horizontal={false} />
                        <XAxis type="number" tick={{ fill: C.muted, fontSize: 10 }} axisLine={false} tickLine={false} />
                        <YAxis type="category" dataKey="area" tick={{ fill: C.text, fontSize: 11 }} axisLine={false} tickLine={false} width={100} />
                        <Tooltip content={({ active, payload, label }) => {
                          if (!active || !payload?.length) return null;
                          return <div style={{ background: C.surface, border: `1px solid ${C.border}`, borderRadius: 8, padding: "8px 12px" }}>
                            <div style={{ fontSize: 12, color: C.muted, marginBottom: 4 }}>{label}</div>
                            <div style={{ fontSize: 13, color: C.teal, ...styles.mono }}>{fmtNum(payload[0].value)} units</div>
                          </div>;
                        }} />
                        <Bar dataKey="units" name="Units" radius={[0, 4, 4, 0]}>
                          {areaD.byArea.slice(0, 8).map((_, i) => (
                            <Cell key={i} fill={YEAR_COLORS[i % YEAR_COLORS.length]} />
                          ))}
                        </Bar>
                      </BarChart>
                    </ResponsiveContainer>
                  </div>
                </div>

                {/* Area detail table */}
                <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: 12, padding: 20 }}>
                  <SectionHeader title={`Area Detail — ${areaYr}`} sub="Sorted by revenue" />
                  <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                    <thead>
                      <tr>
                        {['#','Area','Revenue ex-GST','Rev %','Units','Invoices','Customers','Gross Profit','GP Margin'].map(h => (
                          <th key={h} style={{ textAlign: ['#','Area'].includes(h) ? 'left' : 'right', padding: "8px 12px", color: C.muted, borderBottom: `1px solid ${C.border}`, fontWeight: 600, fontSize: 11, letterSpacing: '0.04em', whiteSpace: 'nowrap' }}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {areaD.byArea.map((a, i) => (
                        <tr key={a.area} style={{ background: i % 2 === 0 ? C.surface : 'transparent' }}>
                          <td style={{ padding: "9px 12px", color: C.muted, ...styles.mono }}>{i + 1}</td>
                          <td style={{ padding: "9px 12px", color: C.text, fontWeight: 600 }}>
                            <span style={{ display: 'inline-block', width: 8, height: 8, borderRadius: '50%', background: YEAR_COLORS[i % YEAR_COLORS.length], marginRight: 8 }} />
                            {a.area}
                          </td>
                          <td style={{ padding: "9px 12px", color: C.teal, textAlign: 'right', ...styles.mono }}>{fmtAUDf(a.revenue)}</td>
                          <td style={{ padding: "9px 12px", color: C.muted, textAlign: 'right', ...styles.mono }}>{fmtPct(a.revenue / totalAreaRev * 100)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(Math.round(a.units))}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(a.invoices)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono }}>{fmtNum(a.customers)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, color: a.profit < 0 ? C.rose : C.gold }}>{fmtAUDf(a.profit)}</td>
                          <td style={{ padding: "9px 12px", textAlign: 'right', ...styles.mono, fontWeight: 700,
                            color: a.margin < 0 ? C.rose : a.margin < 8 ? C.gold : C.green }}>{fmtPct(a.margin)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

              </div>
            );
          })()}
        </>
      )}

      <div style={{ textAlign: 'center', marginTop: 32, fontSize: 11, color: C.dim }}>
        Tyre Retail Intelligence Dashboard • Data stays in your browser • Upload new monthly files anytime
      </div>
    </div>
  );
}
