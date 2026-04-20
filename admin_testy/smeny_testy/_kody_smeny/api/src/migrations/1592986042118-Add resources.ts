import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import addResourceCategories from "./scripts/addResourceCategories";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1592986042118 implements MigrationInterface {
    name = 'AddResources1592986042118'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("DROP INDEX `IDX_3142bfbe5ab5395d166b103264` ON `shift_hour`", undefined);
        await addResourceCategories(queryRunner, [{name: "SHIFT_WEEK_TEMPLATES", label: "Plánování směn - Šablony"}]);
        await addResources(queryRunner, [{
            name: "WEEK_PLANNING_COPY_FROM_TEMPLATE",
            label: "Kopírování ze šablon",
            categoryName: "WEEK_PLANNING",
            description: "Kopírování směn ze šablon",
            requiredResource: ["WEEK_PLANNING_SEE"]
        }, {
            name: "SHIFT_WEEK_TEMPLATES_SEE",
            label: "Zobrazení",
            categoryName: "SHIFT_WEEK_TEMPLATES",
            description: "Zobrazení šablon"
        }, {
            name: "SHIFT_WEEK_TEMPLATES_ADD",
            label: "Přidávání",
            categoryName: "SHIFT_WEEK_TEMPLATES",
            description: "Přidávání šablon",
            requiredResource: ["SHIFT_WEEK_TEMPLATES_SEE"]
        }, {
            name: "SHIFT_WEEK_TEMPLATES_EDIT",
            label: "Upravování",
            categoryName: "SHIFT_WEEK_TEMPLATES",
            description: "Upravování šablon",
            requiredResource: ["SHIFT_WEEK_TEMPLATES_SEE"]
        }, {
            name: "SHIFT_WEEK_TEMPLATES_DELETE",
            label: "Odstraňování",
            categoryName: "SHIFT_WEEK_TEMPLATES",
            description: "Odstraňování šablon",
            requiredResource: ["SHIFT_WEEK_TEMPLATES_SEE"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE UNIQUE INDEX `IDX_3142bfbe5ab5395d166b103264` ON `shift_hour` (`preferredHourId`)", undefined);
        await removeResources(queryRunner, ["WEEK_PLANNING_COPY_FROM_TEMPLATE", "SHIFT_WEEK_TEMPLATES_SEE", "SHIFT_WEEK_TEMPLATES_ADD", "SHIFT_WEEK_TEMPLATES_EDIT", "SHIFT_WEEK_TEMPLATES_DELETE"]);
        await removeResourceCategories(queryRunner, ["SHIFT_WEEK_TEMPLATES"]);
    }

}
