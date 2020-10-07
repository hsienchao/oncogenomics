library(edgeR)
library(dplyr)
library(tibble)

getCountObj <- function(x){
  #print(paste(x))
  featureCountRDS <- readRDS(x)
  return(featureCountRDS$count)
}

fpkmToTpm <- function(fpkm){
    exp(log(fpkm) - log(sum(fpkm)) + log(1e6))
  }


normalize <- function(countObj, workdir="", annotName = "", method="", fileName="", annotationRDS="", Refcol =1, priorCount=0, merge=T, saveFiles=F,
                        condition=""){

    Annotation <- data.frame(readRDS(annotationRDS))

    #drop       <- which( apply(countObj,1,sum) <= 1); print(paste("Droping ", length(drop))) ; countObj <- countObj[-drop,]
    #Annotation <- Annotation[-drop,]
        
    if(priorCount == 0) { tolog = FALSE}  else{ tolog = TRUE }
    
    ########################################### Choose the Annotation
    #print(paste("Chooseing the Annotation"))
    if( annotName == "exon")
    {
      #print(paste("I am Exon"))
      rownames(Annotation) <- Annotation$ExonID
      Annotation$ExonID    <- factor(Annotation$ExonID, levels=rownames(countObj))
      Annotation           <- Annotation %>% dplyr::arrange(ExonID)
      
      genesObj <- Annotation[,c("ExonID", "Length")]
    }
    else if(annotName == "trans"){
      rownames(Annotation) <- Annotation$TranscriptID
      Annotation$TranscriptID <- factor(Annotation$TranscriptID, levels=rownames(countObj))
      Annotation <- Annotation %>% dplyr::arrange(TranscriptID)      
      genesObj <- Annotation[,c("TranscriptID", "Length")]
    }
    else{
      rownames(Annotation) <- Annotation$GeneID
      Annotation$GeneID <- factor(Annotation$GeneID, levels=rownames(countObj))
      Annotation           <- Annotation %>% dplyr::arrange(GeneID)
      Annotation <- Annotation %>% dplyr::filter(GeneID %in% rownames(countObj))
      genesObj <- Annotation[,c("GeneID", "Length")]
    }
    
    ########################################### Choose the Method 
    #print(paste("Chooseing the Normalization Method"))
    if( method=="EdgeR") {
    
   #### EdgeR
      ##  Make EdgeR Object
      colnames(genesObj) <- c("GeneID", "Length")
      length(countObj)
      length(genesObj)
      GeneDF_EdgeR       <- DGEList(counts=countObj, genes=genesObj)
      ## Estimate Normalising Factors
      GeneDF.Norm  <- calcNormFactors(GeneDF_EdgeR, refColumn = Refcol) ; 
      ## Regularized Log Transformation using CPM, FPKM & TPM values
      #GeneDF.tpm   <- as.data.frame(cpm(GeneDF.Norm,  normalized.lib.sizes = TRUE,log = tolog, prior.count = priorCount))
      GeneDF.rpkm  <- as.data.frame(rpkm(GeneDF.Norm, normalized.lib.sizes = T, gene.length=genesObj$Length))
      GeneDF.tpm   <- apply(rpkm(GeneDF.Norm, normalized.lib.sizes = T, gene.length=genesObj$Length), 2 , fpkmToTpm)
      #GeneDF.ScaledTpm <- t(t(GeneDF.tpm) / GeneDF.Norm$samples$norm.factors)
      #GeneDF.ScaledTpm <- GeneDF.tpm
    }
    else if( method == "DESeq") {
    #### DESeq
      ##Make DESeq Object
      GeneDF_DESeq      <- DESeqDataSetFromMatrix(countData = as.matrix(countObj), colData = DataFrame(condition), design = ~ condition)
      mcols(GeneDF_DESeq)$basepairs <- genesObj$Length-175+1
      ## Estimate SizeFactors
      GeneDF.Norm <- estimateSizeFactors(GeneDF_DESeq)
      ## Regularized Log Transformation using CPM, FPKM & TPM values
      GeneDF.fpm   <- as.data.frame(fpm(object = GeneDF.Norm,  robust = TRUE))
      GeneDF.rpkm  <- as.data.frame(fpkm(object = GeneDF.Norm,  robust = TRUE))
      GeneDF.tpm   <- apply(GeneDF.rpkm, 2 , fpkmToTpm)
    }
     
    ########################################### Prepare final files
    if( annotName == "Exon")
    {
      #GeneDF_Norm_CPM  <- cbind(data.frame(Annotation[,c("Chr","Start","End","GeneID", "GeneName","TranscriptID","ExonID")]), GeneDF.CPM )
      GeneDF_Norm_rpkm <- cbind(data.frame(Annotation[,c("Chr","Start","End","GeneID", "GeneName","TranscriptID","ExonID")]), GeneDF.rpkm )
      GeneDF_Norm_tpm  <- cbind(data.frame(Annotation[,c("Chr","Start","End","GeneID", "GeneName","TranscriptID","ExonID")]), GeneDF.tpm )
      
    }
    else if(annotName == "trans"){
      
      #GeneDF_Norm_CPM  <- cbind(data.frame(Annotation[,c("Chr","Start","End","GeneID","GeneName","TranscriptID")]), GeneDF.CPM )
      GeneDF_Norm_rpkm <- cbind(data.frame(Annotation[,c("TranscriptID", "GeneID", "GeneName")]), GeneDF.rpkm )
      GeneDF_Norm_tpm  <- cbind(data.frame(Annotation[,c("TranscriptID", "GeneID", "GeneName")]), GeneDF.tpm)
      
    }
    else{
      #GeneDF_Norm_CPM  <- cbind(data.frame(Annotation[,c("Chr","Start","End","GeneID","GeneName")]), GeneDF.CPM)
      GeneDF_Norm_rpkm <- cbind(data.frame(Annotation[,c("GeneID", "GeneID", "GeneName")]), GeneDF.rpkm)
      GeneDF_Norm_tpm  <- cbind(data.frame(Annotation[,c("GeneID", "GeneID", "GeneName")]), GeneDF.tpm)
    }
    
    ########################################### Choose approprite folder and write files 
    if(merge) {   RawCount=FPKM=CPM=TPM="/MergedFiles/" }
    else { RawCount="/RawCount/"; FPKM="/FPKM/" ; CPM="/CPM/" ; TPM="/TPM"}
  
    if(saveFiles)
    {
      #write.table(countObj, paste(workdir,fileName,"_Count_",annotName,".txt", sep= ""), sep="\t",row.names = FALSE, quote = FALSE)
      #write.table(GeneDF_Norm_rpkm, paste(workdir,fileName,"_Norm_rpkm_",annotName,".txt", sep= ""), sep="\t",row.names = FALSE, quote = FALSE)
      #write.table(GeneDF_Norm_CPM, paste(workdir, fileName,"_Norm_cpm_",annotName,".txt", sep= ""), sep="\t",row.names = FALSE, quote = FALSE)
      #write.table(GeneDF_Norm_tpm, paste(workdir,fileName,"_Norm_tpm_",annotName,".txt", sep= ""), sep="\t",row.names = FALSE, quote = FALSE)
    }
    return(list("tpm" = GeneDF_Norm_tpm, "rpkm" = GeneDF_Norm_rpkm))
    
}

getColName <- function(x){
  sample <- gsub("Sample_", "", gsub(".*\\.(.*)\\.star_.*", "\\1", c(x)))
  idx <- grep(sample,f[,1])
  if ( identical(idx, integer(0)) ) {
    return (sample)
  }
  return (f[idx[1],2])
}

Args<-commandArgs()
inFile<-Args[6]
annotationRDS <- Args[7]
annotationType <- Args[8]
outDIR <- Args[9]
outFile <- Args[10]

f<-read.table(inFile, header=F)
f<-as.matrix(f)
countObj<- do.call(cbind,lapply(f[,3],getCountObj))
#fileList=c("/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/ProcessedResults/clinomics/CL0045/20160415/CL0045_T2R_T/TPM_UCSC/CL0045_T2R_T_counts.Gene.fc.RDS","/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/ProcessedResults/clinomics/CL0045/20160415/CL0045_T1R_T4/TPM_UCSC/CL0045_T1R_T4_counts.Gene.fc.RDS")
#annotationRDS = "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/data/AnnotationRDS/annotation_UCSC_gene.RDS"

GeneDF_Norm<-normalize(countObj=countObj, workdir=outDIR, annotName=annotationType, method="EdgeR", annotationRDS=annotationRDS , 
  priorCount=0, fileName=outFile, merge=T, saveFiles=T)

old_cols <- colnames(GeneDF_Norm$tpm, do.NULL = FALSE)
new_cols <-lapply(old_cols, getColName) 
colnames(GeneDF_Norm$tpm) <- new_cols
colnames(GeneDF_Norm$rpkm) <- new_cols

write.table(GeneDF_Norm$tpm, paste(outDIR,outFile,".tpm.tsv", sep= ""), sep="\t",row.names = FALSE, quote = FALSE)
write.table(GeneDF_Norm$rpkm, paste(outDIR,outFile,".tmm-rpkm.tsv", sep= ""), sep="\t",row.names = FALSE, quote = FALSE)
