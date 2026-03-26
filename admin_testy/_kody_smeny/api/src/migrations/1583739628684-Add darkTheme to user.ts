import {MigrationInterface, QueryRunner} from "typeorm";

export class AddDarkThemeToUser1583739628684 implements MigrationInterface {
    name = 'AddDarkThemeToUser1583739628684'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` ADD `darkTheme` tinyint NOT NULL DEFAULT 0", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `darkTheme`", undefined);
    }

}
