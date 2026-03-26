import { gql } from 'apollo-boost';

const PREFERRED_WEEK_FIND_ALL_IN_WEEK = gql`
  query($skipWeeks: Int!) {
    preferredWeekFindAllInWeek(skipWeeks: $skipWeeks) {
      id
      startDay
      lastEditTime
      user {
        id
        name
        surname
        workingBranches {
          id
          name
        }
        totalEvaluationScore
        shiftRoleTypeNames
      }
      preferredDays {
        id
        day
        preferredHours {
          id
          startHour
          notAssigned
          visible
        }
      }
    }
    globalSettingsFindDayStart {
      id
      value
    }
    branchFindAll {
      id
      name
    }
    shiftRoleTypeFindAll {
      id
      name
    }
  }
`;

export default PREFERRED_WEEK_FIND_ALL_IN_WEEK;
