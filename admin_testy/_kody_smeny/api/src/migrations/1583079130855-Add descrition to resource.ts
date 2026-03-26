import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddDescritionToResource1583079130855 implements MigrationInterface {
  name = 'AddDescritionToResource1583079130855';

  public async up(queryRunner: QueryRunner): Promise<any> {
    await queryRunner.query(
      'ALTER TABLE `resource` ADD `description` varchar(255) NOT NULL',
      undefined,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<any> {
    await queryRunner.query('ALTER TABLE `resource` DROP COLUMN `description`', undefined);
  }
}
