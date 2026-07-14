import { gql } from 'apollo-boost';

const shiftHoursFragment = gql`
  fragment ShiftHours on ShiftRole {
    shiftHours {
      id
      startHour
      confirmed
      isFirst
      employee {
        id
        name
        surname
        hasOwnCar
      }
    }
  }
`;

export default shiftHoursFragment;
