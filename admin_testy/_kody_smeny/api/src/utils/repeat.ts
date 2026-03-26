import { registerEnumType } from 'type-graphql';

enum Repeat {
  never,
  daily,
  weekly,
  monthly,
  yearly,
}

registerEnumType(Repeat, {
  name: 'Repeat',
});
export default Repeat;
