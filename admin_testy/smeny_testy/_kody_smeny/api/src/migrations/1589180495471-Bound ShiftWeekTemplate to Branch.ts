import {MigrationInterface, QueryRunner} from "typeorm";
import ShiftWeekTemplate from "../shiftWeekTemplate/shiftWeekTemplate.entity";

export class BoundShiftWeekTemplateToBranch1589180495471 implements MigrationInterface {
    name = 'BoundShiftWeekTemplateToBranch1589180495471'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_f0d2e45e55d17f557aa0e9f7c49`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `userId`", undefined);

        const templateRepository = queryRunner.manager.getRepository(ShiftWeekTemplate)
        const templates = await templateRepository.find()
        for (const template of templates) {
            template.active = false
            await templateRepository.save(template)
        }
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `userId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_f0d2e45e55d17f557aa0e9f7c49` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

}

