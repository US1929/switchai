/**
 * Validatori per form sottoscrizione energia
 * CF, IBAN, POD, PDR, CAP, telefono
 */

/** Valida Codice Fiscale italiano (16 caratteri) */
export function isValidCF(cf) {
  if (!cf) return false;
  const re = /^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i;
  if (!re.test(cf)) return false;

  // Algoritmo di checksum
  const oddMap = { 0:1,1:0,2:5,3:7,4:9,5:13,6:15,7:17,8:19,9:21, A:1,B:0,C:5,D:7,E:9,F:13,G:15,H:17,I:19,J:21,K:2,L:4,M:18,N:20,O:11,P:3,Q:6,R:8,S:12,T:14,U:16,V:10,W:22,X:25,Y:24,Z:23 };
  const evenMap = { 0:0,1:1,2:2,3:3,4:4,5:5,6:6,7:7,8:8,9:9, A:0,B:1,C:2,D:3,E:4,F:5,G:6,H:7,I:8,J:9,K:10,L:11,M:12,N:13,O:14,P:15,Q:16,R:17,S:18,T:19,U:20,V:21,W:22,X:23,Y:24,Z:25 };

  let sum = 0;
  for (let i = 0; i < 15; i++) {
    const c = cf[i].toUpperCase();
    sum += i % 2 === 0 ? (oddMap[c] ?? 0) : (evenMap[c] ?? 0);
  }
  const check = String.fromCharCode(65 + (sum % 26));
  return check === cf[15].toUpperCase();
}

/** Valida Partita IVA italiana (11 cifre) */
export function isValidPIVA(pi) {
  if (!pi || !/^\d{11}$/.test(pi)) return false;
  let sum = 0;
  for (let i = 0; i < 11; i++) {
    const d = parseInt(pi[i]);
    sum += i % 2 === 0 ? d : d * 2 - (d > 4 ? 9 : 0);
  }
  return sum % 10 === 0;
}

/** Valida IBAN italiano */
export function isValidIBAN(iban) {
  if (!iban) return false;
  const cleaned = iban.replace(/\s/g, '').toUpperCase();
  if (!/^IT\d{2}[A-Z]\d{10}[0-9A-Z]{12}$/.test(cleaned)) return false;
  const rearranged = cleaned.substring(4) + cleaned.substring(0, 4);
  const numeric = rearranged.split('').map(c => /[A-Z]/.test(c) ? c.charCodeAt(0) - 55 : c).join('');
  let remainder = numeric;
  while (remainder.length > 2) {
    remainder = (parseInt(remainder.slice(0, 9), 10) % 97).toString() + remainder.slice(9);
  }
  return parseInt(remainder, 10) % 97 === 1;
}

/** Formatta IBAN in gruppi da 4 */
export function formatIBAN(iban) {
  return iban.replace(/\s/g, '').toUpperCase().match(/.{1,4}/g)?.join(' ') || iban;
}

/** Valida codice POD (LUCE): IT + 3 cifre + E + 8-9 cifre (standard ARERA) */
export function isValidPOD(pod) {
  if (!pod) return false;
  return /^IT\d{3}[Ee]\d{8,9}$/.test(pod.trim());
}

/** Valida codice PDR (GAS): 14 cifre (ARERA standard) */
export function isValidPDR(pdr) {
  if (!pdr) return false;
  return /^\d{14}$/.test(pdr.replace(/\s/g, ''));
}

/** Valida CAP italiano */
export function isValidCAP(cap) {
  return /^\d{5}$/.test(cap?.trim());
}

/** Valida telefono italiano */
export function isValidPhone(phone) {
  return /^(\+39\s?)?3\d{2}[\s-]?\d{3}[\s-]?\d{4}$/.test(phone?.replace(/\s/g, ''));
}

/** Valida email */
export function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email?.trim());
}
