/**
 * Province italiane — mappa nome → sigla per autocomplete
 */
export const PROVINCE = {
  "Agrigento": "AG", "Alessandria": "AL", "Ancona": "AN", "Aosta": "AO",
  "Arezzo": "AR", "Ascoli Piceno": "AP", "Asti": "AT", "Avellino": "AV",
  "Bari": "BA", "Barletta-Andria-Trani": "BT", "Belluno": "BL", "Benevento": "BN",
  "Bergamo": "BG", "Biella": "BI", "Bologna": "BO", "Bolzano": "BZ",
  "Brescia": "BS", "Brindisi": "BR", "Cagliari": "CA", "Caltanissetta": "CL",
  "Campobasso": "CB", "Caserta": "CE", "Catania": "CT", "Catanzaro": "CZ",
  "Chieti": "CH", "Como": "CO", "Cosenza": "CS", "Cremona": "CR",
  "Crotone": "KR", "Cuneo": "CN", "Enna": "EN", "Fermo": "FM",
  "Ferrara": "FE", "Firenze": "FI", "Foggia": "FG", "Forlì-Cesena": "FC",
  "Frosinone": "FR", "Genova": "GE", "Gorizia": "GO", "Grosseto": "GR",
  "Imperia": "IM", "Isernia": "IS", "La Spezia": "SP", "L'Aquila": "AQ",
  "Latina": "LT", "Lecce": "LE", "Lecco": "LC", "Livorno": "LI",
  "Lodi": "LO", "Lucca": "LU", "Macerata": "MC", "Mantova": "MN",
  "Massa-Carrara": "MS", "Matera": "MT", "Messina": "ME", "Milano": "MI",
  "Modena": "MO", "Monza e Brianza": "MB", "Napoli": "NA", "Novara": "NO",
  "Nuoro": "NU", "Oristano": "OR", "Padova": "PD", "Palermo": "PA",
  "Parma": "PR", "Pavia": "PV", "Perugia": "PG", "Pesaro e Urbino": "PU",
  "Pescara": "PE", "Piacenza": "PC", "Pisa": "PI", "Pistoia": "PT",
  "Pordenone": "PN", "Potenza": "PZ", "Prato": "PO", "Ragusa": "RG",
  "Ravenna": "RA", "Reggio Calabria": "RC", "Reggio Emilia": "RE", "Rieti": "RI",
  "Rimini": "RN", "Roma": "RM", "Rovigo": "RO", "Salerno": "SA",
  "Sassari": "SS", "Savona": "SV", "Siena": "SI", "Siracusa": "SR",
  "Sondrio": "SO", "Taranto": "TA", "Teramo": "TE", "Terni": "TR",
  "Torino": "TO", "Trapani": "TP", "Trento": "TN", "Treviso": "TV",
  "Trieste": "TS", "Udine": "UD", "Varese": "VA", "Venezia": "VE",
  "Verbano-Cusio-Ossola": "VB", "Vercelli": "VC", "Verona": "VR", "Vibo Valentia": "VV",
  "Vicenza": "VI", "Viterbo": "VT"
};

/** Trova suggerimenti provincia dato un input parziale */
export function suggestProvince(query) {
  if (!query || query.length < 1) return [];
  const q = query.toLowerCase();
  return Object.entries(PROVINCE)
    .filter(([nome, sigla]) =>
      nome.toLowerCase().startsWith(q) || sigla.toLowerCase().startsWith(q)
    )
    .slice(0, 8);
}

/** Titoli immobile (enum per form) */
export const TITOLI_IMMOBILE = [
  { value: 'Proprietario', label: 'Proprietario' },
  { value: 'Affittuario', label: 'Affittuario' },
  { value: 'Comodatario', label: 'Comodatario' },
  { value: 'Usufruttuario', label: 'Usufruttuario' },
];

/** Modalità pagamento */
export const MODALITA_PAGAMENTO = [
  { value: 'SDD', label: 'Domiciliazione bancaria (SDD)' },
  { value: 'Bollettino', label: 'Bollettino postale' },
];
