import {QueryRunner} from "typeorm";
import ResourceCategory from "../../resourceCategory/resourceCategory.entity";

const addResourceCategory = async (queryRunner: QueryRunner, name: string, label: string) => {
    const resourceCategoryRepository = queryRunner.manager.getRepository(ResourceCategory);
    const category = new ResourceCategory();
    category.name = name;
    category.label = label;
    await resourceCategoryRepository.save(category)
};

export default addResourceCategory