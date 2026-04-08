import {Injectable} from '@nestjs/common';
import {Option} from './option.schema';
import {InjectModel} from '@nestjs/mongoose';
import {Model} from 'mongoose';
import {OptionDto, OptionsDto} from './options.dto';
import {I18nService} from 'nestjs-i18n';
import {ExErrorException} from 'src/common/error.filter';

@Injectable()
export class OptionsService {
    constructor(
        @InjectModel(Option.name) private optionModel: Model<Option>,
        private readonly i18n: I18nService
    ) {
    }

    async getOption(optionName: string, lang?: string): Promise<Option | null> {
        const result = await this.optionModel.findOne({name: optionName.trim(), autoLoad: 1, lang}).exec();
        if (!result) {
            throw new ExErrorException(
                'RECORD_NOT_FOUND',
                404,
                this.i18n.translate('errors.RECORD_NOT_FOUND', {lang: `${lang}`})
            );
        }
        return result;

    }

    async getOptions(optionNames: string[], lang?: string): Promise<Option[]> {
        const cleanedOptionNames = optionNames.map(name => name.trim());
        const options = await this.optionModel.find({name: {$in: cleanedOptionNames}, autoLoad: 1, lang}).exec();
        if (!options.length) {
            throw new ExErrorException(
                await this.i18n.translate('errors.FETCH_RECORDS_FAIL', {lang}),
                404 // Using 404 for "Not Found"
            );
        }

        return options;
    }


    async create(optionDto: OptionDto, lang?: string): Promise<Option | null> {
        try {
            const newOption = new this.optionModel(optionDto);
            return await newOption.save();
        } catch (error) {
            throw new ExErrorException(
                'CREATE_RECORD_FAIL',
                500,
                await this.i18n.translate('errors.CREATE_RECORD_FAIL', {lang}),
                error.message
            );
        }

    }

    async createMulti(optionDto: OptionsDto, lang?: string): Promise<Option[] | null> {
        try {
            const result = await this.optionModel.insertMany(optionDto.options);
            return result || [];
        } catch (error) {
            throw new ExErrorException(
                'CREATE_RECORD_FAIL',
                500,
                this.i18n.translate('errors.CREATE_RECORD_FAIL', {lang}),
                error.message
            );
        }
    }

    async update(optionDto: OptionDto, lang?: string): Promise<Option | null> {
        const option = await this.optionModel.findOne({name: optionDto.name, lang}).exec();
        if (!option) {
            throw new ExErrorException(
                'RECORD_NOT_FOUND',
                404,
                this.i18n.translate('errors.RECORD_NOT_FOUND', {lang})
            );
        }
        try {
            await this.optionModel.updateOne(
                {name: optionDto.name, lang: optionDto.lang},
                {$set: {value: optionDto.value, autoLoad: optionDto.autoLoad}}
            ).exec();

            return this.optionModel.findOne({name: optionDto.name}).exec();
        } catch (error) {
            throw new ExErrorException(
                'SERVER_ERROR',
                500,
                this.i18n.translate('errors.SERVER_ERROR', {lang}),
                error.message
            )
        }
    }

    async updateMulti(optionDto: OptionsDto, lang?: string): Promise<Option[] | null> {
        try {
            const updatePromises = optionDto.options.map(async (option) => {
                const existingOption = await this.optionModel.findOne({name: option.name, lang}).exec();

                if (!existingOption) {
                    console.log(option.name)
                    throw new ExErrorException(
                        'RECORD_NOT_FOUND',
                        404,
                        this.i18n.translate('errors.RECORD_NOT_FOUND', {lang})
                    );
                }

                await this.optionModel.updateOne(
                    {name: option.name},
                    {$set: {value: option.value, autoLoad: option.autoLoad}}
                ).exec();
            });

            // Wait for all updates to complete
            await Promise.all(updatePromises);

            // Return updated records
            return await this.optionModel.find({name: {$in: optionDto.options.map(opt => opt.name)}}).exec();
        } catch (error) {
            console.log(error.message)
            throw new ExErrorException(
                `UPDATE_RECORD_FAIL`,
                500,
                this.i18n.translate('errors.UPDATE_RECORD_FAIL', {lang}),
                error.message
            );
        }
    }


    async delete(optionName: string, lang?: string): Promise<Option | null> {
        const option = await this.optionModel.findOne({name: optionName, lang}).exec();
        if (!option) {
            throw new ExErrorException('RECORD_NOT_FOUND', 404,
                this.i18n.translate('errors.RECORD_NOT_FOUND', {lang})
            );
        }
        try {
            await this.optionModel.deleteOne({name: optionName, lang}).exec();
            return option;

        } catch (error) {
            // console.log(error.message)
            throw new ExErrorException(
                `DELETE_RECORD_FAIL`,
                500,
                this.i18n.translate('errors.DELETE_RECORD_FAIL', {lang}),
                error.message
            );
        }
    }

    async deleteMulti(optionNames: string[], lang?: string): Promise<Option[] | null> {
        // Find options before deleting them
        const cleanedOptionNames = optionNames.map(name => name.trim());
        const options = await this.optionModel.find({name: {$in: cleanedOptionNames}, lang}).exec();

        if (!options.length) {
            throw new ExErrorException(
                'RECORD_NOT_FOUND',
                404,
                this.i18n.translate('errors.RECORD_NOT_FOUND', { lang })
            );
        }
        try {
            // Delete options in one query
            await this.optionModel.deleteMany({ name: {$in: cleanedOptionNames}, lang }).exec();
            return options; // Return deleted options
        } catch (error) {
            // console.log(error.message)
            throw new ExErrorException(
                `DELETE_RECORD_FAIL`,
                500,
                this.i18n.translate('errors.DELETE_RECORD_FAIL', {lang}),
                error.message
            );
        }
    }

}
