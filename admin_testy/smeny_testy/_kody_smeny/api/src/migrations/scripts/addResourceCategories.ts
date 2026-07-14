import {QueryRunner} from "typeorm";
import addResourceCategory from "./addResourceCategory";

const addResourceCategories = async (queryRunner: QueryRunner, resourceCategoryNames: { name: string, label: string }[]) => {
    for (const category of resourceCategoryNames) {
        await addResourceCategory(queryRunner, category.name, category.label);
    }
};

export default addResourceCategories;