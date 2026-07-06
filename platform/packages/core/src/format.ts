function addCommas(s: string): string {
  return s.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

export function formatMinor(amountMinor: string, currency: 'USD' | 'PAWS'): string {
  const amount = BigInt(amountMinor);

  if (currency === 'USD') {
    const dollars = amount / 100n;
    const cents = amount % 100n;
    const dollarsStr = addCommas(dollars.toString());
    const centsStr = cents.toString().padStart(2, '0');
    return `$${dollarsStr}.${centsStr}`;
  }

  // PAWS: integer with thousands separators
  return `${addCommas(amount.toString())} PAWS`;
}
