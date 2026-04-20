const apiErrors = {
  input: {
    invalid: 'INPUT_INVALID',
  },
  remove: {
    roleMinimalCount: 'REMOVE_ROLE_MINIMAL_COUNT',
    resourceConditions: 'REMOVE_RESOURCE_CONDITIONS',
  },
  role: {
    maxUsers: 'ROLE_MAX_USERS',
  },
  db: {
    duplicate: 'ER_DUP_ENTRY',
  },
  shiftRole: {
    hoursOutOfRange: 'SHIFT_ROLE_HOURS_OUT_OF_RANGE',
    someWorkerDoNotHaveShiftRoleType:
      'SHIFT_ROLE_SOME_WORKER_DO_NOT_HAVE_SHIFT_ROLE_TYPE',
    notEmpty: 'SHIFT_ROLE_NOT_EMPTY',
  },
  evaluation: {
    cooldown: 'EVALUATION_COOLDOWN',
  },
};

export default apiErrors;
