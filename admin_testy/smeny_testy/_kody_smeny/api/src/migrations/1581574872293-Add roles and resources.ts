import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddRolesAndResources1581574872293 implements MigrationInterface {
  name = 'AddRolesAndResources1581574872293';

  public async up(queryRunner: QueryRunner): Promise<any> {
    await queryRunner.query(
      'CREATE TABLE `resource` (`id` int NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB',
      undefined,
    );
    await queryRunner.query(
      'CREATE TABLE `role` (`id` int NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB',
      undefined,
    );
    await queryRunner.query(
      'CREATE TABLE `role_resources_resource` (`roleId` int NOT NULL, `resourceId` int NOT NULL, INDEX `IDX_5c4b4c9b01b043a8ea20176e5f` (`roleId`), INDEX `IDX_f00890ff7ebee1a10632b353d2` (`resourceId`), PRIMARY KEY (`roleId`, `resourceId`)) ENGINE=InnoDB',
      undefined,
    );
    await queryRunner.query(
      'ALTER TABLE `role_resources_resource` ADD CONSTRAINT `FK_5c4b4c9b01b043a8ea20176e5ff` FOREIGN KEY (`roleId`) REFERENCES `role`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION',
      undefined,
    );
    await queryRunner.query(
      'ALTER TABLE `role_resources_resource` ADD CONSTRAINT `FK_f00890ff7ebee1a10632b353d28` FOREIGN KEY (`resourceId`) REFERENCES `resource`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION',
      undefined,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<any> {
    await queryRunner.query(
      'ALTER TABLE `role_resources_resource` DROP FOREIGN KEY `FK_f00890ff7ebee1a10632b353d28`',
      undefined,
    );
    await queryRunner.query(
      'ALTER TABLE `role_resources_resource` DROP FOREIGN KEY `FK_5c4b4c9b01b043a8ea20176e5ff`',
      undefined,
    );
    await queryRunner.query(
      'DROP INDEX `IDX_f00890ff7ebee1a10632b353d2` ON `role_resources_resource`',
      undefined,
    );
    await queryRunner.query(
      'DROP INDEX `IDX_5c4b4c9b01b043a8ea20176e5f` ON `role_resources_resource`',
      undefined,
    );
    await queryRunner.query('DROP TABLE `role_resources_resource`', undefined);
    await queryRunner.query('DROP TABLE `role`', undefined);
    await queryRunner.query('DROP TABLE `resource`', undefined);
  }
}
