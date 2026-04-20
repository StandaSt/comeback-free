import { gql } from 'apollo-boost';

const CREATE_TIME_NOTIFICATION_MUTATION = gql`
  mutation {
    timeNotificationCreate(
      repeat: never
      name: "Nová notifikace"
      message: ""
    ) {
      id
    }
  }
`;
export default CREATE_TIME_NOTIFICATION_MUTATION;
