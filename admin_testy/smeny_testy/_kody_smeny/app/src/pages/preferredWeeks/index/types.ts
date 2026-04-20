export interface PreferredWeekGetRelevant {
  preferredWeekGetRelevant: {
    id: number;
    startDay: Date;
    lastEditTime?: Date;
  }[];
  userGetLogged: {
    workingBranchesCount: number;
  };
  globalSettingsFindPreferredDeadline: {
    id: number;
    value: string;
  };
}
