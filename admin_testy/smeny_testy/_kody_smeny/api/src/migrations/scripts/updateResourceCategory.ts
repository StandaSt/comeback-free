import ResourceCategory from "resourceCategory/resourceCategory.entity";
import {QueryRunner} from "typeorm";

const updateResourceCategory = async (queryRunner: QueryRunner, name: string, update: { label?: string, name?: string }) => {
    const resourceCategoryRepository = queryRunner.manager.getRepository(ResourceCategory);
    const category = await resourceCategoryRepository.findOne({name});
    if (update.label)
        category.label = update.label;
    if (update.name)
        category.label = update.name;
    await resourceCategoryRepository.save(category)
}

export default updateResourceCategory