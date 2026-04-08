import { Injectable } from '@nestjs/common';
import { CreateFaqDto, CreateQuestionDto } from './dtos/create.faq.dto';
import { Faq } from './faq.schema';
import { I18nService } from 'nestjs-i18n';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { ExErrorException } from 'src/common/error.filter';
import { FilterFaqDto } from './dtos/filter.faq.dto';
import { parseListString, parseNumberList } from 'src/utils/parse.util';

@Injectable()
export class FaqService {
    constructor(
        @InjectModel(Faq.name) private readonly faqModel: Model<Faq>,
        private readonly i18n: I18nService
    ) { }

    async create(createFaqDto: CreateFaqDto, userId?: String, lang?: string): Promise<Faq> {
        try {
            let faq = new this.faqModel(createFaqDto);
            if (userId) {
                faq = new this.faqModel({ ...createFaqDto, author: userId, answerby: userId });
            }
            return await faq.save();

        } catch (error) {
            const msg = this.i18n.translate('errors.CREATE_RECORD_FAIL', { lang });

            throw new ExErrorException(
                'CREATE_RECORD_FAIL',
                500,
                msg,
                error.message
            );
        }
    }

    async createQuestion(createFaqDto: CreateQuestionDto, userId?: String, lang?: string): Promise<Faq> {
        try {
            let faq = new this.faqModel(createFaqDto);
            if (userId) {
                faq = new this.faqModel({ ...createFaqDto, answerby: userId });
            }
            return await faq.save();

        } catch (error) {
            const msg = this.i18n.translate('errors.CREATE_RECORD_FAIL', { lang });

            throw new ExErrorException(
                'CREATE_RECORD_FAIL',
                500,
                msg,
                error.message
            );
        }
    }

    async findAll(query: FilterFaqDto, lang?: string): Promise<{
        data: Faq[];
        meta: {
            page: number;
            limit: number;
            total: number;
            pageCount: number;
        };
    } | null> {

        try {
            const {
                page = 1,
                limit = 10,
                lang,
                status,
                exclude,
                reviews,
                views,
                pinTop,
                category,
                keyword,
                startDate, endDate, sort = 'desc'
            } = query;

            const filter: Record<string, any> = {};
            const orFilters: Record<string, any>[] = [];
            const sortObj: any = {};
            const _status = parseListString(query.status);
            const cats = parseNumberList(query.category);

            if (lang) filter.lang = lang;
            if (_status?.length) {
                if (_status.length > 1) {
                    orFilters.push(..._status.map((s) => ({ status: s })));
                } else {
                    filter.status = { $in: _status };
                }
            }

            if (orFilters.length) {
                filter.$or = orFilters;
            }

            // console.log('filter:', JSON.stringify(filter, null, 2));

            if (cats.length) filter.categories = { $in: cats };

            if (keyword) {
                filter.$or = [
                    { question: { $regex: keyword, $options: 'i' } },
                    { answer: { $regex: keyword, $options: 'i' } },
                ];
            }

            if (startDate || endDate) {
                filter.createdAt = {};

                if (startDate) {
                    const start = new Date(startDate);
                    start.setHours(0, 0, 0, 0);
                    filter.createdAt.$gte = start;
                }

                if (endDate) {
                    const end = new Date(endDate);
                    end.setHours(23, 59, 59, 999);
                    filter.createdAt.$lte = end;
                }
            }

            const _excludeList = parseListString(query.exclude);
            if (_excludeList?.length) {
                filter._id = { $nin: _excludeList };
            }

            if (query.pinTop === true) {
                // Ưu tiên các bản ghi có pintop = true lên đầu
                sortObj.pinTop = -1;
            } else if (query.reviews === true) {
                // Sắp xếp theo số lượng reviews nếu có yêu cầu 
                sortObj.reviews = -1;
            } else if (query.views === true) {
                // Sắp xếp theo số lượng views nếu có yêu cầu 
                sortObj.views = -1;
            } else {
                sortObj.createdAt = query.sort === 'asc' ? 1 : -1;
            }

            const total = await this.faqModel.countDocuments(filter);
            const data = await this.faqModel
                .find(filter)
                .populate('author', ['email', 'niceName', 'displayName', 'avatar'])
                .populate('answerby', ['email', 'niceName', 'displayName', 'avatar'])
                .skip((page - 1) * limit)
                .limit(limit)
                .sort(sortObj)
                .exec();

            return {
                data,
                meta: {
                    page,
                    limit,
                    total,
                    pageCount: Math.ceil(total / limit),
                },
            };
        } catch (error) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                500,
                msg,
                error.message
            );
        }
    }

    async findOneById(id: string, lang?: string): Promise<Faq | null> {
        const result = await this.faqModel.findOneAndUpdate(
            { _id: id, lang },
            { $inc: { views: 1 } },
            { new: true }
        ).populate('answerby', ['email', 'niceName', 'displayName']).exec();
        if (!result) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                404,
                msg,
            );
        }
        return result;
    }

    async update(id: string, updateDto: Partial<CreateFaqDto>, userId?: string, lang?: string): Promise<Faq | null> {
        const fetch = await this.faqModel.findOne({ _id: id, lang }).exec();
        if (!fetch) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                404,
                msg
            );
        }

        try {
            let dto = updateDto;
            if (userId) dto = { ...updateDto, answerby: userId };
            // console.log(userId, dto);
            const result = await this.faqModel.findOneAndUpdate({ _id: id, lang }, dto, {
                new: true,
            }).exec();

            return result;
        } catch (error) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                500,
                msg,
                error.message
            );
        }
    }

    async pinTop(id: string, lang?: string): Promise<Faq | null> {
        const fetch = await this.faqModel.findOne({ _id: id, lang }).exec();
        if (!fetch) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                404,
                msg
            );
        }

        try {
            const result = await this.faqModel.findOneAndUpdate(
                { _id: id, lang },
                { pinTop: true },
                { new: true }).exec();

            return result;
        } catch (error) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                500,
                msg,
                error.message
            );
        }
    }

    async delete(id: string, lang?: string): Promise<Faq | null> {
        const fetch = await this.faqModel.findOne({ _id: id, lang }).exec();
        if (!fetch) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                404,
                msg,
            );
        }
        try {
            await this.faqModel.deleteOne({ _id: id, lang }).exec();
            return fetch;
        } catch (error) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                404,
                msg
            );
        }
    }
}
