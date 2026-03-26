interface GlobalSettingsItem {
  id: number;
  value: string;
}
export interface GlobalSettings {
  globalSettingsFindDayStart: GlobalSettingsItem;
  globalSettingsFindPreferredWeeksAhead: GlobalSettingsItem;
  globalSettingsFindPreferredDeadline: GlobalSettingsItem;
  globalSettingsFindEvaluationCooldown: GlobalSettingsItem;
  globalSettingsFindEvaluationTTL: GlobalSettingsItem;
  globalSettingsDeadlineNotification: GlobalSettingsItem;
}

export interface FormTypes {
  dayStart: string;
  weeksAhead: string;
  evaluationTTL: string;
  evaluationCooldown: string;
  deadlineNotification: string;
}

export interface GlobalSettingsChange {
  globalSettingsChangeDayStart: GlobalSettingsItem;
  globalSettingsChangePreferredWeeksAhead: GlobalSettingsItem;
  globalSettingsChangePreferredDeadline: GlobalSettingsItem;
  globalSettingsChangeEvaluationCooldown: GlobalSettingsItem;
  globalSettingsChangeEvaluationTTL: GlobalSettingsItem;
  globalSettingsChangeDeadlineNotification: GlobalSettingsItem;
}

export interface GlobalSettingsChangeVars {
  dayStart: number;
  preferredWeeksAhead: number;
  preferredDeadline: string;
  cooldown: number;
  ttl: number;
  deadlineNotification: number;
}

export interface PreferredDeadlineProps {
  deadline: Date;
  editing: boolean;
  onDeadlineChange: (deadline: Date) => void;
}
