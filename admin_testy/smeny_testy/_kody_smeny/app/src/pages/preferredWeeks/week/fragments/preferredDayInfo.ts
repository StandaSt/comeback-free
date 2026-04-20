import { gql } from 'apollo-boost';

const preferredDayInfoFragment = gql`
  fragment PreferredDayInfo on PreferredDay {
    id
    day
    preferredHours {
      id
      startHour
      visible
    }
  }
`;

export default preferredDayInfoFragment;
