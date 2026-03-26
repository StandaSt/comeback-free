import {MigrationInterface, QueryRunner} from "typeorm";

export class MakeStartDayNullable1588879316401 implements MigrationInterface {
    name = 'MakeStartDayNullable1588879316401'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` CHANGE `startDay` `startDay` datetime NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` CHANGE `startDay` `startDay` datetime NULL", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` CHANGE `startDay` `startDay` datetime NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` CHANGE `startDay` `startDay` datetime NOT NULL", undefined);
    }

}
