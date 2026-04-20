import {MigrationInterface, QueryRunner} from "typeorm";

export class AddPublishedToShiftWeek1588841358622 implements MigrationInterface {
    name = 'AddPublishedToShiftWeek1588841358622'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` ADD `published` tinyint NOT NULL DEFAULT 0", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `published`", undefined);
    }

}
