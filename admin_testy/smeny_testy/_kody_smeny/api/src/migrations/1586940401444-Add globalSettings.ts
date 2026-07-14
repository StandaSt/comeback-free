import {MigrationInterface, QueryRunner} from "typeorm";

export class AddGlobalSettings1586940401444 implements MigrationInterface {
    name = 'AddGlobalSettings1586940401444'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE TABLE `global_settings` (`id` int NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `value` varchar(255) NOT NULL, UNIQUE INDEX `IDX_fd6a23c6683883d3a4e6f11a90` (`name`), PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("INSERT INTO `global_settings` (name, value) VALUES ('dayStart', '8')");
        await queryRunner.query("INSERT INTO `global_settings` (name, value) VALUES ('preferredWeeksAhead', '4')");

    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("DELETE FROM `global_settings` WHERE name='dayStart'");
        await queryRunner.query("DELETE FROM `global_settings` WHERE name='preferredWeeksAhead'");
        await queryRunner.query("DROP INDEX `IDX_fd6a23c6683883d3a4e6f11a90` ON `global_settings`", undefined);
        await queryRunner.query("DROP TABLE `global_settings`", undefined);
    }

}
