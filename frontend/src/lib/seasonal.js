import { LUCE } from './constants.js';

export const MESI = ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];

export const PUN_SEASONAL = [1.12, 1.08, 1.04, 0.96, 0.92, 0.90, 0.88, 0.86, 0.92, 0.98, 1.06, 1.10];
export const PSV_SEASONAL = [1.30, 1.20, 1.10, 0.90, 0.70, 0.55, 0.50, 0.50, 0.65, 0.85, 1.05, 1.25];

export const CONSUMO_LUCE = [0.090, 0.082, 0.088, 0.080, 0.078, 0.082, 0.090, 0.094, 0.082, 0.080, 0.076, 0.078];
export const CONSUMO_GAS  = [0.16,  0.14,  0.12,  0.06,  0.02,  0.01,  0.01,  0.01,  0.03,  0.08,  0.14,  0.22];

export function buildMonthlyProjection({
  commodity,
  consumption,
  userSpread,
  userQuotaFissa,
  currentPun,
  currentPsv,
  monthlyTrendPct,
  avgMarketSpread,
  avgMarketQuotaFissa,
  avgFixedPricePerUnit,
  billBreakdown,
  currentAnnualSpend,
}) {
  if (!consumption || consumption <= 0 || !currentAnnualSpend) return null;

  const isLuce = commodity === 'luce';
  const seasonal = isLuce ? PUN_SEASONAL : PSV_SEASONAL;
  const consumoDist = isLuce ? CONSUMO_LUCE : CONSUMO_GAS;
  const losses = isLuce ? LUCE.PERDITE_RETE_BT : 1;
  const trend = monthlyTrendPct || 0;
  const currentIndex = isLuce ? (currentPun || 0.125) : (currentPsv || 0.45);
  const now = new Date();
  const currentMonth = now.getMonth();

  const costiFissiAnnui = (billBreakdown?.trasporto || 0) + (billBreakdown?.oneri || 0) + (billBreakdown?.imposte || 0);
  const qfAnnua = (userQuotaFissa || 0) * 12;
  const materiaAnnua = Math.max(1, currentAnnualSpend - costiFissiAnnui - qfAnnua);

  const effectiveSpread = userSpread != null ? userSpread
    : (materiaAnnua / (consumption * losses) - currentIndex / seasonal[currentMonth]);

  // Compute normalized PUN/PSV monthly factors (sum to 12)
  const rawFactors = seasonal.map((s, m) => {
    const tf = 1 + (trend / 100) * Math.max(0, 1 - m / 8);
    return (s / seasonal[currentMonth]) * tf;
  });
  const avgFactor = rawFactors.reduce((s, f) => s + f, 0) / 12;
  const normFactors = rawFactors.map(f => f / avgFactor);

  const hasFixedPrice = avgFixedPricePerUnit != null && avgFixedPricePerUnit > 0.01;
  const avgQF = avgMarketQuotaFissa || (userQuotaFissa || 0);

  const months = [];

  for (let m = 0; m < 12; m++) {
    const meseConsumo = consumption * consumoDist[m];
    const punMese = (currentIndex / seasonal[currentMonth]) * normFactors[m];

    const energiaTua = meseConsumo * (punMese * losses + effectiveSpread);
    const baseFissa = (userQuotaFissa || 0) + (costiFissiAnnui / 12);
    const costoTuaRaw = energiaTua + baseFissa;

    // Comparison: flat fixed price or spread-based
    let costoTuaRawAvg;
    if (hasFixedPrice) {
      // Fixed offers have a flat price per kWh/Smc (no PUN fluctuation)
      costoTuaRawAvg = meseConsumo * avgFixedPricePerUnit + avgQF + (costiFissiAnnui / 12);
    } else {
      const avgSpread = avgMarketSpread || effectiveSpread;
      const energiaAvg = meseConsumo * (punMese * losses + avgSpread);
      costoTuaRawAvg = energiaAvg + avgQF + (costiFissiAnnui / 12);
    }

    months.push({
      month: m,
      label: MESI[m],
      consumoMese: Math.round(meseConsumo),
      punMese,
      costoTuaRaw,
      costoTuaRawAvg,
    });
  }

  // Normalize to anchor to known currentAnnualSpend
  const rawTotal = months.reduce((s, m) => s + m.costoTuaRaw, 0);
  const scale = rawTotal > 0 ? currentAnnualSpend / rawTotal : 1;

  for (const m of months) {
    const ct = Math.max(0, Math.round(m.costoTuaRaw * scale));
    const ca = Math.max(0, Math.round(m.costoTuaRawAvg * scale));
    m.costoTua = ct;
    m.costoMedio = ca;
    m.risparmio = ct - ca;
    delete m.costoTuaRaw;
    delete m.costoTuaRawAvg;
  }

  return months;
}
