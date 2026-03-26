export interface ShiftDay {
  id: number;
  day: string;
  shiftRoles: {
    id: number;
    halfHour: boolean;
    type: {
      id: number;
      name: string;
      sortIndex: number;
      color: string;
    };
    shiftHours: {
      id: number;
      startHour: number;
      confirmed?: boolean;
      isFirst: boolean;
      employee?: {
        id: number;
        name: string;
        surname: string;
        hasOwnCar?: boolean;
      };
    }[];
  }[];
}

export interface ShiftDays {
  shiftDays: ShiftDay[];
}
