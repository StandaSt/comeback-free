export interface ColorPickerProps {
  value: string;
  label?: string;
  onChange: (c: string) => void;
  variant?: 'outlined' | 'filled' | 'standard';
}
