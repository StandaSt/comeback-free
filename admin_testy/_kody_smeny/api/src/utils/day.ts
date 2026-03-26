import { registerEnumType } from 'type-graphql';

enum Day {
  monday,
  tuesday,
  wednesday,
  thursday,
  friday,
  saturday,
  sunday,
}

registerEnumType(Day, {
  name: 'Day',
});
export default Day;
