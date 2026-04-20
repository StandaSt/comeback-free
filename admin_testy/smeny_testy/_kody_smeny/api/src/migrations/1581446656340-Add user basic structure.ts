import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddUserBasicStructure1581446656340 implements MigrationInterface {
  name = 'AddUserBasicStructure1581446656340';

  public async up(queryRunner: QueryRunner): Promise<any> {
    await queryRunner.query(
      'CREATE TABLE `user` (`id` int NOT NULL AUTO_INCREMENT, `email` varchar(255) NOT NULL, `passwordIsHashed` tinyint NOT NULL DEFAULT 1, `password` varchar(255) NOT NULL, `name` varchar(255) NOT NULL, `surname` varchar(255) NOT NULL, UNIQUE INDEX `IDX_e12875dfb3b1d92d7d7c5377e2` (`email`), PRIMARY KEY (`id`)) ENGINE=InnoDB',
      undefined,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<any> {
    await queryRunner.query('DROP INDEX `IDX_e12875dfb3b1d92d7d7c5377e2` ON `user`', undefined);
    await queryRunner.query('DROP TABLE `user`', undefined);
  }
}
