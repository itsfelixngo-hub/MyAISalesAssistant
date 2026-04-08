export function parseNumberList(str?: string): number[] {
    return (str || '')
      .split(',')
      .map(v => parseInt(v.trim(), 10))
      .filter(v => !isNaN(v));
  }
export function parseListString(value?: string): string[] {
    return value?.split(',').map((v) => v.trim()).filter(Boolean) || [];
  }
  